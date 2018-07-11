<?php

namespace Shelby\Payment;

class PayPalTest extends PayPal {

	protected $_api_endpoint = 'https://api-3t.sandbox.paypal.com/nvp';
	protected $_pp_url = 'https://www.sandbox.paypal.com/webscr&cmd=_express-checkout&token=';

}