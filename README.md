# MPESA Payment Gateway for PHPNuxBill

![MPESA Logo](mpesa/assets/mpesa.png)

This plugin integrates MPESA mobile money payments with PHPNuxBill billing system.

## Features

- MPESA Paybill and Till Number support
- STK Push payment initiation
- Automatic payment verification
- Sandbox and Production modes
- Detailed payment instructions for customers

## Installation

1. Download the latest release from the [Releases page](https://github.com/yourusername/mpesa-gateway-phpnuxbill/releases)
2. Extract the zip file
3. Upload the `mpesa` folder to your PHPNuxBill `modules/gateways/` directory
4. Go to PHPNuxBill admin panel > Payment Gateways
5. Activate the MPESA Gateway
6. Configure with your MPESA API credentials

## Requirements

- PHPNuxBill 1.0 or higher
- PHP 7.2+ with cURL extension
- MPESA API credentials (from Safaricom Developer Portal)
- Valid SSL certificate for production use

## Configuration

1. Obtain API credentials from [Safaricom Developer Portal](https://developer.safaricom.co.ke)
2. In PHPNuxBill, go to Payment Gateways and configure MPESA with:
   - Business Shortcode
   - Consumer Key
   - Consumer Secret
   - Passkey
   - Environment (Sandbox/Production)
3. Set your callback URL in MPESA API settings to: `https://yourdomain.com/callback/mpesa`

## Support

For issues or feature requests, please [open an issue](https://github.com/yourusername/mpesa-gateway-phpnuxbill/issues).

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

## License

[MIT](LICENSE)
