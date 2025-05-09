<?php
/**
 * MPESA Payment Gateway for PHPNuxBill
 * 
 * @package    mpesa
 * @version    1.0.0
 * @author     Your Name <your@email.com>
 * @license    MIT License
 * @copyright  2023 Your Name
 * @link       https://github.com/yourusername/mpesa-gateway-phpnuxbill
 */

if (!defined('PHPNUXBILL')) {
    die('Direct access not allowed');
}

/**
 * Plugin configuration
 */
function mpesa_config() {
    return [
        "name" => "MPESA Payment Gateway",
        "description" => "MPESA mobile money payment gateway for PHPNuxBill",
        "version" => "1.0.0",
        "author" => "Your Name",
        "website" => "https://yourwebsite.com",
        "fields" => [
            "mpesa_shortcode" => [
                "title" => "Business Shortcode",
                "type" => "text",
                "size" => "20",
                "description" => "Your MPESA Paybill or Till Number",
                "required" => true
            ],
            "mpesa_key" => [
                "title" => "Consumer Key",
                "type" => "text",
                "size" => "50",
                "description" => "From Safaricom Developer Portal",
                "required" => true
            ],
            "mpesa_secret" => [
                "title" => "Consumer Secret",
                "type" => "password",
                "size" => "50",
                "description" => "From Safaricom Developer Portal",
                "required" => true
            ],
            "mpesa_passkey" => [
                "title" => "Passkey",
                "type" => "password",
                "size" => "50",
                "description" => "From Safaricom Developer Portal",
                "required" => true
            ],
            "mpesa_callback_url" => [
                "title" => "Callback URL",
                "type" => "text",
                "size" => "100",
                "description" => "Set this in your MPESA API settings",
                "default" => U.'callback/mpesa',
                "readonly" => true
            ],
            "mpesa_env" => [
                "title" => "Environment",
                "type" => "dropdown",
                "options" => [
                    "sandbox" => "Sandbox",
                    "production" => "Production"
                ],
                "description" => "Select MPESA API environment",
                "default" => "sandbox"
            ],
            "mpesa_stk_push" => [
                "title" => "Enable STK Push",
                "type" => "yesno",
                "description" => "Initiate payment directly from customer's phone",
                "default" => "yes"
            ]
        ]
    ];
}

/**
 * Generate payment link/button
 */
function mpesa_link($params) {
    // Load language file
    global $_L;
    include __DIR__.'/lang/en.php';
    
    // Gateway parameters
    $shortcode = $params['mpesa_shortcode'];
    $stkEnabled = $params['mpesa_stk_push'] == 'yes';
    
    // Invoice parameters
    $invoiceid = $params['invoiceid'];
    $amount = $params['amount'];
    $currency = $params['currency'];
    $description = $params['description'];
    $clientPhone = $params['clientdetails']['phonenumber'] ?? '';
    
    // System parameters
    $systemurl = $params['systemurl'];
    
    // Generate payment instructions
    $html = '<div class="mpesa-payment-container">';
    
    if ($stkEnabled && !empty($clientPhone)) {
        // STK Push option
        $html .= '<div class="mpesa-stk-push">
            <h4>'.$_L['mpesa_push_payment'].'</h4>
            <button id="mpesa-push-btn" class="btn btn-success" 
                data-invoice="'.$invoiceid.'" 
                data-amount="'.$amount.'" 
                data-phone="'.$clientPhone.'">
                '.$_L['mpesa_initiate_payment'].'
            </button>
            <div id="mpesa-push-status"></div>
        </div>';
        
        // Add JavaScript for STK Push
        $html .= '<script>
        document.getElementById("mpesa-push-btn").addEventListener("click", function() {
            const btn = this;
            const statusDiv = document.getElementById("mpesa-push-status");
            const invoiceId = btn.dataset.invoice;
            const amount = btn.dataset.amount;
            const phone = btn.dataset.phone;
            
            btn.disabled = true;
            statusDiv.innerHTML = "'.$_L['mpesa_processing'].'";
            
            fetch("'.$systemurl.'callback/mpesa/initiate", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    invoice_id: invoiceId,
                    amount: amount,
                    phone: phone
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.innerHTML = "'.$_L['mpesa_push_success'].'";
                    // Redirect to invoice page after 5 seconds
                    setTimeout(() => {
                        window.location.href = "'.$systemurl.'view-invoice/" + invoiceId;
                    }, 5000);
                } else {
                    statusDiv.innerHTML = data.message || "'.$_L['mpesa_push_failed'].'";
                    btn.disabled = false;
                }
            })
            .catch(error => {
                statusDiv.innerHTML = "'.$_L['mpesa_network_error'].'";
                btn.disabled = false;
            });
        });
        </script>';
    }
    
    // Manual payment instructions
    $html .= '<div class="mpesa-manual-payment">
        <h4>'.$_L['mpesa_manual_payment'].'</h4>
        <ol>
            <li>'.$_L['mpesa_step1'].'</li>
            <li>'.$_L['mpesa_step2'].'</li>
            <li>'.$_L['mpesa_step3'].'</li>
            <li>'.sprintf($_L['mpesa_step4'], $shortcode).'</li>
            <li>'.sprintf($_L['mpesa_step5'], $invoiceid).'</li>
            <li>'.sprintf($_L['mpesa_step6'], $amount, $currency).'</li>
            <li>'.$_L['mpesa_step7'].'</li>
        </ol>
        <p>'.$_L['mpesa_processing_time'].'</p>
    </div>';
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Get MPESA API access token
 */
function getMpesaAccessToken($key, $secret, $env) {
    $url = ($env == 'production') 
        ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
        : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    
    $credentials = base64_encode($key.':'.$secret);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic '.$credentials
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}
