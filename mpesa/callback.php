<?php
/**
 * MPESA Callback Handler
 */

if (!defined('PHPNUXBILL')) {
    die('Direct access not allowed');
}

require_once __DIR__.'/../../system/loader.php';
require_once __DIR__.'/../../system/helpers.php';

// Handle STK Push initiation
if (strpos($_SERVER['REQUEST_URI'], '/callback/mpesa/initiate') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $invoiceId = $input['invoice_id'] ?? null;
    $amount = $input['amount'] ?? null;
    $phone = $input['phone'] ?? null;
    
    if (!$invoiceId || !$amount || !$phone) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }
    
    // Get gateway configuration
    $gateway = ORM::for_table('tbl_payment_gateways')
        ->where('name', 'mpesa')
        ->find_one();
        
    if (!$gateway) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gateway not configured']);
        exit;
    }
    
    $config = json_decode($gateway['config'], true);
    
    // Format phone number (2547...)
    $phone = preg_replace('/^0/', '254', $phone);
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Initiate STK Push
    $accessToken = getMpesaAccessToken(
        $config['mpesa_key'],
        $config['mpesa_secret'],
        $config['mpesa_env']
    );
    
    $url = ($config['mpesa_env'] == 'production')
        ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
        : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    
    $timestamp = date('YmdHis');
    $password = base64_encode($config['mpesa_shortcode'].$config['mpesa_passkey'].$timestamp);
    $callbackUrl = rtrim($config['mpesa_callback_url'], '/');
    
    $payload = [
        'BusinessShortCode' => $config['mpesa_shortcode'],
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone,
        'PartyB' => $config['mpesa_shortcode'],
        'PhoneNumber' => $phone,
        'CallBackURL' => $callbackUrl,
        'AccountReference' => 'INV'.$invoiceId,
        'TransactionDesc' => 'Payment for invoice '.$invoiceId
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer '.$accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode == 200 && isset($result['ResponseCode']) && $result['ResponseCode'] == '0') {
        echo json_encode([
            'success' => true,
            'message' => 'Payment request sent to your phone'
        ]);
    } else {
        $error = $result['errorMessage'] ?? $result['ResponseDescription'] ?? 'Unknown error';
        echo json_encode([
            'success' => false,
            'message' => 'Failed to initiate payment: '.$error
        ]);
    }
    exit;
}

// Handle MPESA callback
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

if (!empty($data)) {
    $resultCode = $data['Body']['stkCallback']['ResultCode'] ?? null;
    $resultDesc = $data['Body']['stkCallback']['ResultDesc'] ?? 'Unknown';
    $merchantRequestID = $data['Body']['stkCallback']['MerchantRequestID'] ?? null;
    $checkoutRequestID = $data['Body']['stkCallback']['CheckoutRequestID'] ?? null;
    
    if ($resultCode == 0) {
        $callbackMetadata = $data['Body']['stkCallback']['CallbackMetadata']['Item'] ?? [];
        
        $amount = null;
        $mpesaReceiptNumber = null;
        $phoneNumber = null;
        $transactionDate = null;
        
        foreach ($callbackMetadata as $item) {
            switch ($item['Name']) {
                case 'Amount':
                    $amount = $item['Value'];
                    break;
                case 'MpesaReceiptNumber':
                    $mpesaReceiptNumber = $item['Value'];
                    break;
                case 'PhoneNumber':
                    $phoneNumber = $item['Value'];
                    break;
                case 'TransactionDate':
                    $transactionDate = $item['Value'];
                    break;
            }
        }
        
        // Extract invoice ID from AccountReference (INV123)
        $accountReference = $data['Body']['stkCallback']['AccountReference'] ?? '';
        $invoiceId = preg_replace('/[^0-9]/', '', $accountReference);
        
        if ($invoiceId && $amount && $mpesaReceiptNumber) {
            $invoice = ORM::for_table('tbl_invoices')->find_one($invoiceId);
            
            if ($invoice && $invoice->status != 'Paid') {
                // Update invoice
                $invoice->status = 'Paid';
                $invoice->datepaid = date('Y-m-d H:i:s');
                $invoice->save();
                
                // Record transaction
                $transaction = ORM::for_table('tbl_transactions')->create();
                $transaction->invoice_id = $invoiceId;
                $transaction->user_id = $invoice->user_id;
                $transaction->amount = $amount;
                $transaction->payment_method = 'MPESA';
                $transaction->trans_id = $mpesaReceiptNumber;
                $transaction->created_at = date('Y-m-d H:i:s');
                $transaction->save();
                
                // Log activity
                logActivity("MPESA Payment Received - Invoice #$invoiceId - Amount: $amount - Receipt: $mpesaReceiptNumber");
                
                // Send payment confirmation email
                sendPaymentConfirmation($invoiceId);
            }
        }
    }
    
    // Always respond with success to MPESA
    http_response_code(200);
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Success']);
    exit;
}

http_response_code(400);
echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Failed']);
