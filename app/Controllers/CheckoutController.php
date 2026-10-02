<?php
namespace App\Controllers;

use App\Core\ValidationException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\SepayTransaction;
use App\Models\User;
use App\Services\SepayGateway;
use App\Services\VnpayGateway;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class CheckoutController extends BaseController
{
    /**
     * Phương thức đang hỗ trợ. Momo / PayOS chưa tích hợp.
     */
    const ENABLED_METHODS = ['sepay', 'vnpay', 'cash'];

    public function show($bookingId)
    {
        $bookingModel = new Booking();
        $bookingModel->releaseExpired();
        $booking = $this->findOwnBooking($bookingId);

        if ($booking['status'] === 'paid' || $bookingModel->hasPendingCashPayment($booking['id'])) {
            return $this->redirect('/booking/' . $booking['id'] . '/confirmation');
        }

        return $this->render('customer.checkout', [
            'booking'   => $booking,
            'user'      => (new User())->getUserById(\AuthHelper::id()),
            'seats'     => $bookingModel->getSeats($booking['id']),
            'products'  => $bookingModel->getProducts($booking['id']),
            'payable'   => $bookingModel->isPayable($booking),
            'vnpayReady' => (new VnpayGateway())->isConfigured(),
            'sepayReady' => (new SepayGateway())->isConfigured() && (new SepayGateway())->hasApiKey(),
        ]);
    }

    public function pay($bookingId)
    {
        $booking = $this->findOwnBooking($bookingId);
        $method = $_POST['method'] ?? '';
        $checkoutPath = '/checkout/' . $booking['id'];

        if (!in_array($method, self::ENABLED_METHODS, true)) {
            return $this->flashRedirect($checkoutPath, 'Phương thức thanh toán không hợp lệ', 'danger');
        }
        if ($method === 'cash' && !\App\Models\Setting::cashEnabled()) {
            return $this->flashRedirect($checkoutPath, 'Rạp đang tạm ngưng nhận thanh toán tại quầy', 'danger');
        }

        $gateway = new VnpayGateway();
        if ($method === 'vnpay' && !$gateway->isConfigured()) {
            return $this->flashRedirect($checkoutPath, 'Thanh toán VNPay chưa được cấu hình', 'danger');
        }
        $sepay = new SepayGateway();
        if ($method === 'sepay' && !($sepay->isConfigured() && $sepay->hasApiKey())) {
            return $this->flashRedirect($checkoutPath, 'Thanh toán chuyển khoản chưa được cấu hình', 'danger');
        }

        $bookingModel = new Booking();
        $bookingModel->releaseExpired();
        $paymentModel = new Payment();
        $this->db->beginTransaction();
        try {
            $detail = $bookingModel->lockForPayment($booking['id'], \AuthHelper::id());
            // Mỗi đơn chỉ 1 payment SePay đang chờ (giữ nguyên nội dung CK nếu chọn lại);
            // đổi sang cách khác thì hủy payment chuyển khoản cũ
            $existing = $method === 'sepay' ? $paymentModel->findPending($detail['id'], 'sepay') : null;
            if ($existing && empty($existing['transferCode'])) {
                $existing = null;
            }
            if ($method !== 'sepay' || !$existing) {
                $paymentModel->cancelPending($detail['id'], 'sepay');
            }
            if ($existing) {
                $paymentId = $existing['id'];
            } elseif ($method === 'sepay') {
                $paymentId = $paymentModel->createSepayPending($detail['id'], $detail['totalAmount']);
            } else {
                $paymentId = $paymentModel->createPending($detail['id'], $method, $detail['totalAmount']);
            }
            if ($method === 'cash') {
                // Giữ ghế tới giờ chiếu mà chưa trả tiền: giới hạn số đơn để 1 tài khoản không chiếm hết phòng
                if ($bookingModel->countCashHolds(\AuthHelper::id(), $detail['id'], $detail['showtimeId']) > 0) {
                    throw new ValidationException('Bạn đã có 1 đơn trả tại quầy cho suất này. Vui lòng thanh toán online cho đơn mới.');
                }
                if ($bookingModel->countCashHolds(\AuthHelper::id(), $detail['id']) >= Booking::MAX_CASH_HOLDS) {
                    throw new ValidationException('Bạn đang có ' . Booking::MAX_CASH_HOLDS . ' đơn chờ trả tại quầy. Vui lòng thanh toán online hoặc hủy bớt đơn cũ.');
                }
                $bookingModel->extendForCash($detail['id'], $detail['startTime']);
            }
            $this->db->commit();
        } catch (ValidationException $e) {
            $this->db->rollBack();
            return $this->flashRedirect($checkoutPath, $e->getMessage(), 'danger');
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        if ($method === 'cash') {
            return $this->flashRedirect(
                '/booking/' . $detail['id'] . '/confirmation',
                'Đặt vé thành công. Vui lòng thanh toán tại quầy trước giờ chiếu.',
                'success'
            );
        }

        if ($method === 'sepay') {
            return $this->redirect('/checkout/' . $detail['id'] . '/transfer');
        }

        $url = $gateway->createPaymentUrl(
            $paymentId,
            $detail['totalAmount'],
            'Thanh toan ve ' . $detail['code'],
            \CommonHelper::url('checkout/' . $detail['id'] . '/callback'),
            strtotime($detail['expiredAt']),
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        );

        return $this->redirectAway($url);
    }

    /**
     * VNPay chuyển trình duyệt của khách về đây sau khi thanh toán
     */
    public function paymentCallback($bookingId)
    {
        $booking = $this->findOwnBooking($bookingId);
        $confirmationPath = '/booking/' . $booking['id'] . '/confirmation';
        $result = $this->processVnpayResult($_GET, (int)$booking['id']);

        switch ($result) {
            case 'paid':
            case 'already':
                return $this->flashRedirect($confirmationPath, 'Thanh toán thành công', 'success');
            case 'late':
                return $this->flashRedirect(
                    '/checkout/' . $booking['id'],
                    'Đơn đã hết hạn giữ ghế trước khi thanh toán hoàn tất. Vui lòng liên hệ rạp để được hoàn tiền.',
                    'warning'
                );
            case 'failed':
                return $this->flashRedirect('/checkout/' . $booking['id'], 'Thanh toán không thành công hoặc đã bị hủy', 'warning');
            default:
                return $this->flashRedirect('/checkout/' . $booking['id'], 'Dữ liệu thanh toán không hợp lệ', 'danger');
        }
    }

    /**
     * VNPay IPN (server gọi server): public, không có session, trả JSON theo đặc tả VNPay
     */
    public function vnpayIpn()
    {
        $result = $this->processVnpayResult($_GET, null);
        $responses = [
            'paid'      => ['00', 'Confirm Success'],
            'failed'    => ['00', 'Confirm Success'],
            'late'      => ['00', 'Confirm Success'],
            'already'   => ['02', 'Order already confirmed'],
            'signature' => ['97', 'Invalid signature'],
            'notfound'  => ['01', 'Order not found'],
            'amount'    => ['04', 'Invalid amount'],
        ];
        [$code, $message] = $responses[$result] ?? ['99', 'Unknown error'];

        return $this->json(['RspCode' => $code, 'Message' => $message]);
    }

    /**
     * Trang chuyển khoản: mã QR VietQR + thông tin tài khoản, tự kiểm tra trạng thái
     */
    public function transfer($bookingId)
    {
        $bookingModel = new Booking();
        $bookingModel->releaseExpired();
        $booking = $this->findOwnBooking($bookingId);

        if ($booking['status'] === 'paid') {
            return $this->redirect('/booking/' . $booking['id'] . '/confirmation');
        }

        $payment = (new Payment())->findPending($booking['id'], 'sepay');
        // Payment tạo trước khi có transferCode: không có nội dung CK -> quay lại chọn thanh toán để tạo mới
        if (!$payment || empty($payment['transferCode']) || !$bookingModel->isPayable($booking)) {
            return $this->redirect('/checkout/' . $booking['id']);
        }

        $sepay = new SepayGateway();
        $content = $payment['transferCode'];

        return $this->render('customer.transfer', [
            'booking'       => $booking,
            'amount'        => (int)$payment['amount'],
            'content'       => $content,
            'qrUrl'         => $sepay->qrUrl($payment['amount'], $content),
            'bank'          => $sepay->bank(),
            'accountNumber' => $sepay->accountNumber(),
            'accountName'   => $sepay->accountName(),
        ]);
    }

    /**
     * AJAX: trang chuyển khoản hỏi trạng thái mỗi vài giây
     */
    public function paymentStatus($bookingId)
    {
        (new Booking())->releaseExpired();
        $booking = $this->findOwnBooking($bookingId);
        $paid = $booking['status'] === 'paid';

        return $this->success([
            'status'      => $paid ? 'paid' : ((new Booking())->isPayable($booking) ? 'pending' : 'expired'),
            'redirectUrl' => \CommonHelper::url($paid ? 'booking/' . $booking['id'] . '/confirmation' : 'checkout/' . $booking['id']),
        ]);
    }

    /**
     * Webhook SePay (server gọi server). Xác thực bằng API key.
     * SePay coi là thành công khi HTTP 200/201 + {"success": true}; ngược lại sẽ retry.
     * Mọi giao dịch hợp lệ (kể cả không khớp đơn) đều trả success để SePay không gửi lại mãi.
     */
    public function sepayWebhook()
    {
        $sepay = new SepayGateway();
        if (!$sepay->verifyAuthorization($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null)) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $tx = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($tx) || !isset($tx['id']) || !is_numeric($tx['id']) || (int)$tx['id'] <= 0) {
            return $this->json(['success' => false, 'message' => 'Invalid payload'], 400);
        }

        $txModel = new SepayTransaction();
        $txId = $txModel->record($tx);
        if ($txId === null) {
            return $this->json(['success' => true, 'message' => 'Duplicate transaction']);
        }

        [$result, $bookingId, $paymentId] = $this->matchSepayTransaction($sepay, $tx);
        $txModel->setResult($txId, $result, $bookingId, $paymentId);

        if (in_array($result, ['late', 'amount_mismatch', 'payment_missing'], true)) {
            error_log("SePay tx {$tx['id']}: {$result} (booking #{$bookingId}) - cần đối soát thủ công");
        }

        return $this->json(['success' => true, 'message' => $result]);
    }

    /**
     * @return array [result, bookingId|null, paymentId|null]
     */
    private function matchSepayTransaction(SepayGateway $sepay, array $tx)
    {
        if (($tx['transferType'] ?? '') !== 'in') {
            return ['ignored_out', null, null];
        }
        if (!$sepay->isOwnAccount($tx['accountNumber'] ?? null)) {
            return ['wrong_account', null, null];
        }

        // Nội dung CK là mã riêng của từng lần thanh toán (payment.transferCode), không phải mã vé
        $paymentModel = new Payment();
        $payment = null;
        foreach (SepayGateway::extractTransferCodes($tx) as $code) {
            $payment = $paymentModel->findByTransferCode($code);
            if ($payment) {
                break;
            }
        }
        if (!$payment) {
            return ['no_match', null, null];
        }
        if ((int)$tx['transferAmount'] !== (int)$payment['amount']) {
            return ['amount_mismatch', (int)$payment['bookingId'], (int)$payment['id']];
        }
        // Khách chuyển tiền sau khi đã đổi sang cách thanh toán khác: mở lại payment để ghi nhận
        if ($payment['status'] === 'failed') {
            $this->db->execute("UPDATE payment SET status = 'pending' WHERE id = ? AND status = 'failed'", [(int)$payment['id']]);
        }

        $reference = is_scalar($tx['referenceCode'] ?? null) && (string)$tx['referenceCode'] !== ''
            ? 'SEPAY' . $tx['id'] . '-' . $tx['referenceCode']
            : 'SEPAY' . $tx['id'];
        $result = (new Booking())->markPaid($payment['id'], mb_substr($reference, 0, 100), $tx);

        return [$result, (int)$payment['bookingId'], (int)$payment['id']];
    }

    public function confirmation($bookingId)
    {
        $bookingModel = new Booking();
        $booking = $this->findOwnBooking($bookingId);
        $payments = $bookingModel->getPayments($booking['id']);
        $cashPending = $bookingModel->hasPendingCashPayment($booking['id']);

        if ($booking['status'] !== 'paid' && !$cashPending) {
            return $this->redirect('/checkout/' . $booking['id']);
        }

        return $this->render('customer.confirmation', [
            'booking'     => $booking,
            'user'        => (new User())->getUserById(\AuthHelper::id()),
            'seats'       => $bookingModel->getSeats($booking['id']),
            'products'    => $bookingModel->getProducts($booking['id']),
            'payment'     => $payments[0] ?? null,
            'cashPending' => $cashPending && $booking['status'] === 'pending',
            'qrCode'      => $this->qrDataUri($booking['code']),
        ]);
    }

    // ---------------- Helpers ----------------

    /**
     * Xác thực + ghi nhận kết quả VNPay (dùng chung cho return URL và IPN)
     * @return string paid | already | late | failed | signature | notfound | amount
     */
    private function processVnpayResult(array $input, $expectedBookingId)
    {
        if (!(new VnpayGateway())->verify($input)) {
            return 'signature';
        }

        $paymentModel = new Payment();
        $payment = $paymentModel->getById((int)($input['vnp_TxnRef'] ?? 0));
        if (!$payment || $payment['method'] !== 'vnpay'
            || ($expectedBookingId !== null && (int)$payment['bookingId'] !== $expectedBookingId)) {
            return 'notfound';
        }
        if (VnpayGateway::amountOf($input) !== (int)$payment['amount']) {
            return 'amount';
        }
        if ($payment['status'] !== 'pending') {
            return 'already';
        }

        if (!VnpayGateway::isSuccess($input)) {
            $paymentModel->markFailed($payment['id'], $input);
            return 'failed';
        }

        $transactionCode = is_string($input['vnp_TransactionNo'] ?? null) ? 'VNP' . $input['vnp_TransactionNo'] : null;

        return (new Booking())->markPaid($payment['id'], $transactionCode, $input);
    }

    /**
     * Chỉ chủ đơn mới xem được; đơn người khác trả 404 để không lộ id
     */
    private function findOwnBooking($bookingId)
    {
        $id = filter_var($bookingId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $booking = $id ? (new Booking())->getForUser($id, \AuthHelper::id()) : null;
        if (!$booking) {
            $this->notFound();
        }

        return $booking;
    }

    private function qrDataUri($data)
    {
        $options = new QROptions([
            'outputType'  => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel'    => QRCode::ECC_M,
            'imageBase64' => true,
            'addQuietzone' => true,
        ]);

        return (new QRCode($options))->render($data);
    }

    private function flashRedirect($path, $message, $type)
    {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;

        return $this->redirect($path);
    }

    /**
     * Redirect sang cổng thanh toán (URL ngoài). Chỉ dùng với URL do server tự tạo.
     */
    private function redirectAway($url)
    {
        header('Location: ' . $url, true, 302);
        return '';
    }
}
