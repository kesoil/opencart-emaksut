<?php
/*
Copyright 2011  Jani Virta <jani.virta@iqit.fi>
Copyright 2012  Mikko Keskinen <keso@iki.fi>

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License as
published by the Free Software Foundation; either version 2 of
the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

class ControllerPaymentEmaksut extends Controller {
	protected function isHashField($key) {
		$ignoreKeys = array('pmt_sellerid', 'pmt_buyeremail', 'pmt_deliveryemail', 'pmt_rows', 'pmt_charset', 'pmt_charsethttp');
		return substr($key, 0, 4) == 'pmt_' && !in_array($key, $ignoreKeys);
	}

	protected function index() {
    	$this->data['button_confirm'] = $this->language->get('button_confirm');

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		$this->data['action'] = 'https://www.maksuturva.fi/NewPaymentExtended.pmt';

		function viitetarkiste($viite) {
			$viite = strval($viite);
			$paino = array(7, 3, 1);
			$summa = 0;
			for($i=strlen($viite) - 1, $j = 0; $i >= 0; $i--, $j++){
				$summa += (int)$viite[$i] * (int)$paino[$j % 3];
			}
			return (10 - ($summa % 10)) % 10;
		}

		$this->data['order_info'] = $order_info;
		$this->data['cart'] = $this->cart;

		// Toiminnon tunniste (aina NEW_PAYMENT_EXTENDED)
		$this->data['pmt_action'] = 'NEW_PAYMENT_EXTENDED';

		// Maksusanoman sisällön määrittelyversio.
		$this->data['pmt_version'] = '0004';
		// Suomen Maksuturva Oy:n Kauppiaalle tunnistamista varten antama tunnus.
		$this->data['pmt_sellerid'] = $this->config->get('emaksut_sellerid');
		// Kauppiaan maksulle antama yksilöivä tunnus.
		$this->data['pmt_id'] = $order_info['order_id'];
		// Tilausnumero, jolla tilaus löytyy Kauppiaan järjestelmästä ja joka on ostajalla tiedossa.
		$this->data['pmt_orderid'] = $order_info['order_id'];
		// Viitenumero, jota Suomen Maksuturva Oy käyttää hyvittäessään rahat toimituksen jälkeen kauppiaalle.
		$this->data['pmt_reference'] = (1000000000) + $order_info['order_id'] . viitetarkiste((1000000000) + $order_info['order_id']);
		// Maksun eräpäiva (kuluva päivä muodossa dd.mm.yyyy)
		$this->data['pmt_duedate'] = date('d.m.Y');

		// Tilauksen loppusumma toimituskuluineen. Summa tulee esittää aina kahden desimaalin tarkkuudella. Desimaalierottimena käytetään pilkkua esim. 94,80
		// $this->data['pmt_amount'] = str_replace('.', ',', $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false));
		$this->data['pmt_amount'] = '';
		// Maksussa käytettävä valuutta. Aina EUR.
		$this->data['pmt_currency']	= 'EUR';

		// Osoite, mihin käyttäjän selain ohjataan onnistuneen maksun jälkeen.
		$this->data['pmt_okreturn'] = HTTP_SERVER . '/index.php?route=payment/emaksut/callback';
		// Osoite, mihin käyttäjän selain ohjataan virheellisen maksun jälkeen.
		$this->data['pmt_errorreturn'] = HTTP_SERVER . '/index.php?route=payment/emaksut/callbackerror';
		// Osoite, mihin käyttäjän selain ohjataan hänen valitessaan maksun peruutuksen.
		$this->data['pmt_cancelreturn'] = HTTP_SERVER . '/index.php?route=payment/emaksut/callbackcancel';
		// Osoite, mihin käyttäjän selain ohjataan hänen valitessaan maksutavakseen esim. tilisiirron.
		$this->data['pmt_delayedpayreturn'] = HTTP_SERVER . '/index.php?route=payment/emaksut/callbackdelayed';

		// Escrow käytössa (Maksuturva=Y, eMaksut=N)
		$this->data['pmt_escrow'] = 'N';
		// Escrow valittavissa (aina N)
		$this->data['pmt_escrowchangeallowed'] = 'N';

		// Laskutusosoitteen (ostajan) nimi.
		$this->data['pmt_buyername'] = $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
		// Laskutusosoitteen (ostajan) postiosoite (katuosoite tai postilokero).
		$this->data['pmt_buyeraddress'] = $order_info['payment_address_1'] . ' ' . $order_info['payment_address_2'];
		// Laskutusosoitteen (ostajan) postinumero.
		$this->data['pmt_buyerpostalcode'] = $order_info['payment_postcode'];
		// Laskutusosoitteen (ostajan) postinumero.
		$this->data['pmt_buyercity'] = $order_info['payment_city'];
		// Laskutusosoitteen (ostajan) maa.
		$this->data['pmt_buyercountry'] = $order_info['payment_iso_code_2'];
		// Käyttäjän sähköpostiosoite.
		$this->data['pmt_buyeremail'] = $order_info['email'];

		$this->data['pmt_deliveryname'] = $order_info['shipping_firstname'] . ' ' . $order_info['shipping_lastname'];
		$this->data['pmt_deliveryaddress'] = $order_info['shipping_address_1'] . ' ' . $order_info['shipping_address_2'];
		$this->data['pmt_deliverypostalcode'] = $order_info['shipping_postcode'];
		$this->data['pmt_deliverycity'] = $order_info['shipping_city'];
		$this->data['pmt_deliverycountry'] = $order_info['shipping_iso_code_2'];
		$this->data['pmt_deliveryemail'] = $order_info['email'];

		// Myyjän / toimittajan käsittelykulut (n,nn)
		$this->data['pmt_sellercosts'] = '0,00';

		// Tilausrivien lukumäärä
		$this->data['pmt_rows'] = count($this->cart->getProducts());

		$amount = (float)0;
		$count = 1;
		foreach ($this->cart->getProducts() as $product) {
			// Tilausrivin tuotteen nimi (max 40 merkkiä)
			$this->data['pmt_row_name' . $count] = utf8_encode(substr(utf8_decode($product['name']), 0, 40));
			// Tilausrivin tuotteen kuvaus
			$this->data['pmt_row_desc' . $count] = $product['name'];
			// Tilausrivin tuotteen määrä
			$this->data['pmt_row_quantity' . $count] = $product['quantity'];
			// Tilausrivin toimituksen tai palvelun suorituksen ajankohta
			$this->data['pmt_row_deliverydate' . $count] = date('d.m.Y');
			// Tilausrivin tuotteen bruttohinta per yksikkö (ALV mukana)
			$unitPrice = $this->tax->calculate($product['price'], $product['tax_class_id'], true);
			if ($product['quantity'] > 0) {
				$amount += $unitPrice * $product['quantity'];
			}
			$this->data['pmt_row_price_gross' . $count] = number_format($unitPrice, 2, ',', '');
			// Tilausrivin tuotteen nettohinta per yksikkö (ilman ALV:a)
			//$this->data['pmt_row_price_net' . $count] = number_format(round($product['price'], 2), 2, ',', '');
			// Tilausrivin tuotteeseen sovellettu ALV-prosentti
			$this->data['pmt_row_vat' . $count] = number_format($this->tax->getRate($product['tax_class_id']), 2, ',', '');
			// Tilausrivin alennusprosentti
			$this->data['pmt_row_discountpercentage' . $count] = '0,00';
			// Tilausrivin tyyppi (1 = tuote 2 = postikulu 3 = käsittelykulu 4 = räätälöity tuote (ei palautusoikeutta) 5 = palvelu 6 = alennus (rahamäärä))
			$this->data['pmt_row_type' . $count] = 1;
			$count++;
		}

		$this->data['pmt_amount'] = number_format(round($amount, 2), 2, ',', '');

		if ($this->session->data['shipping_method']['cost']) {
			$this->data['pmt_rows']++;

			// Tilausrivin tuotteen nimi
			$this->data['pmt_row_name' . $count] = utf8_encode(substr(utf8_decode($this->session->data['shipping_method']['title']), 0, 40));
			// Tilausrivin tuotteen kuvaus
			$this->data['pmt_row_desc' . $count] = $this->session->data['shipping_method']['title'];
			// Tilausrivin tuotteen määrä
			$this->data['pmt_row_quantity' . $count] = 1;
			// Tilausrivin toimituksen tai palvelun suorituksen ajankohta
			$this->data['pmt_row_deliverydate' . $count] = date('d.m.Y');
			// Tilausrivin tuotteen bruttohinta per yksikkö (ALV mukana)
			$this->data['pmt_row_price_gross' . $count] = number_format($this->tax->calculate($this->session->data['shipping_method']['cost'], $this->session->data['shipping_method']['tax_class_id'], true), 2, ',', '');
			// Tilausrivin tuotteen nettohinta per yksikkö (ilman ALV:a)
			//$this->data['pmt_row_price_net' . $count] = number_format(round($this->session->data['shipping_method']['cost'], 2), 2, ',', '');
			// Tilausrivin tuotteeseen sovellettu ALV-prosentti
			$this->data['pmt_row_vat' . $count] = number_format($this->tax->getRate($this->session->data['shipping_method']['tax_class_id']), 2, ',', '');
			// Tilausrivin alennusprosentti
			$this->data['pmt_row_discountpercentage' . $count] = '0,00';
			// Tilausrivin tyyppi (1 = tuote 2 = postikulu 3 = käsittelykulu 4 = räätälöity tuote (ei palautusoikeutta) 5 = palvelu 6 = alennus (rahamäärä))
			$this->data['pmt_row_type' . $count] = 2;

			// Myyjän / toimittajan käsittelykulut (n,nn)
			$this->data['pmt_sellercosts'] = $this->data['pmt_row_price_gross' . $count];
		}

		// Tarkisteen laskentamerkistö
		$this->data['pmt_charset'] = 'UTF-8';
		// Sisään tulevan datan enkoodaus (ja verkkokaupan enkoodaus selaimen suuntaan)
		$this->data['pmt_charsethttp'] = 'UTF-8';

		$sellerkey = $this->config->get('emaksut_sellerkey');
		$sellerkeyver = $this->config->get('emaksut_sellerkeyver');

		if($this->config->get('emaksut_test')=='1') {
			$this->data['pmt_sellerid'] = 'testikauppias';
			$sellerkey = '11223344556677889900';
			$sellerkeyver = '0';
		}

		$hashFields = array_filter(array_keys($this->data), array('self', 'isHashField'));

		$this->data['pmt_hash'] = '';
		foreach ($hashFields as $hashField) {
			$this->data['pmt_hash'] .= $this->data[$hashField] . '&';
		}
		$this->data['pmt_hash'] .= $sellerkey . '&';
		$this->data['pmt_hash'] = md5($this->data['pmt_hash']);
		$this->data['pmt_hashversion'] = 'MD5';
		$this->data['pmt_keygeneration'] = $sellerkeyver;

		$this->data['back'] = HTTPS_SERVER . 'index.php?route=checkout/payment';

		$this->id = 'payment';

		$this->template = 'default/template/payment/emaksut.tpl';

		$this->render();
	}

	public function callback() {

		$this->language->load('payment/emaksut');

		$this->data['title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));

		$sellerkey = $this->config->get('emaksut_sellerkey');
		$sellerkeyver = $this->config->get('emaksut_sellerkeyver');

		if($this->config->get('emaksut_test')=='1') {
			$this->data['pmt_sellerid'] = 'testikauppias';
			$sellerkey = '11223344556677889900';
			$sellerkeyver = '0';
		}

		$error = '';
		if (!isset($this->request->get['pmt_version']) || $this->request->get['pmt_version'] !== '0004') {
			$error .= 'Väärä sanomaversio: "' . (isset($this->request->get['pmt_version']) ? $this->request->get['pmt_version'] : '') . '"<br />';
		}
		if (!isset($this->request->get['pmt_id'])) {
			$error .= 'Ei maksutunnusta.' . '<br />';
		}
		if (!isset($this->request->get['pmt_reference'])) {
			$error .= 'Ei viitettä.' . '<br />';
		}
		if (!isset($this->request->get['pmt_amount'])) {
			$error .= 'Ei maksumäärää.' . '<br />';
		}
		if (!isset($this->request->get['pmt_hash'])) {
			$error .= 'Ei tarkistesummaa.' . '<br />';
		}
		if (!isset($this->request->get['pmt_currency']) || $this->request->get['pmt_currency'] != 'EUR') {
			$error .= 'Väärä valuutta: "' . (isset($this->request->get['pmt_currency']) ? $this->request->get['pmt_currency'] : '')  . '"<br />';
		}
		if (!$error) {
			$sum = strtoupper(
				md5(
					$this->request->get['pmt_action'] . "&" .
					$this->request->get['pmt_version'] . "&" .
					$this->request->get['pmt_id'] . "&" .
					$this->request->get['pmt_reference'] . "&" .
					$this->request->get['pmt_amount'] . "&" .
					$this->request->get['pmt_currency'] . "&" .
					$this->request->get['pmt_sellercosts'] . "&" .
					$this->request->get['pmt_paymentmethod'] . "&" .
					$this->request->get['pmt_escrow'] . "&" .
					$sellerkey . "&"
				)
			);
			if ($sum != $this->request->get['pmt_hash']) {
				$error .= 'Virheellinen tarkastesumma.' . '<br />';
			}
		}

		$this->data['meta_redirect'] = '';

		if ($error == '') {
			if (!isset($this->request->server['HTTPS']) || ($this->request->server['HTTPS'] != 'on')) {
				$this->data['base'] = HTTP_SERVER;
			} else {
				$this->data['base'] = HTTPS_SERVER;
			}

			$this->data['charset'] = $this->language->get('charset');
			$this->data['language'] = $this->language->get('code');
			$this->data['direction'] = $this->language->get('direction');

			$this->data['heading_title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));

			$this->data['text_response'] = $this->language->get('text_response');
			$this->data['text_return'] = $this->language->get('text_success');
			$this->data['text_return_wait'] = sprintf($this->language->get('text_success_wait'), HTTPS_SERVER . 'index.php?route=checkout/success');

			$this->load->model('checkout/order');

			$this->model_checkout_order->confirm($this->request->get['pmt_id'], $this->config->get('emaksut_order_status_id'));

			$message = '';

			$this->model_checkout_order->update($this->request->get['pmt_id'], $this->config->get('emaksut_order_status_id'), $message, false);

			$this->data['continue'] = HTTPS_SERVER . 'index.php?route=checkout/success';
			$this->data['meta_redirect'] = '<meta http-equiv="refresh" content="5;url=' . $this->data['continue'] . '">';

			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/emaksut_return.tpl')) {
				$this->template = $this->config->get('config_template') . '/template/payment/emaksut_return.tpl';
			} else {
				$this->template = 'default/template/payment/emaksut_return.tpl';
			}

	  		$this->response->setOutput($this->render(true), $this->config->get('config_compression'));
		} else {
			$this->data['heading_title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));
			$this->data['continue'] = HTTPS_SERVER . 'index.php?route=checkout/cart';
			$this->data['text_response'] = $this->language->get('text_response');
			$this->data['text_return'] = $this->language->get('text_failure') . "<br/>" . $error;
			$this->data['text_return_wait'] = sprintf($this->language->get('text_failure_wait'), HTTPS_SERVER . 'index.php?route=checkout/cart');

			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/emaksut_return.tpl')) {
				$this->template = $this->config->get('config_template') . '/template/payment/emaksut_return.tpl';
			} else {
				$this->template = 'default/template/payment/emaksut_return.tpl';
			}

			$this->response->setOutput($this->render(true), $this->config->get('config_compression'));
		}
	}

	public function callbackdelayed() {

		$this->language->load('payment/emaksut');

		$this->data['title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));

		$error = '';
		if (!isset($this->request->get['pmt_id'])) {
			$error .= 'Ei maksutunnusta';
		}

		if ($error == '') {
			if (!isset($this->request->server['HTTPS']) || ($this->request->server['HTTPS'] != 'on')) {
				$this->data['base'] = HTTP_SERVER;
			} else {
				$this->data['base'] = HTTPS_SERVER;
			}

			$this->data['charset'] = $this->language->get('charset');
			$this->data['language'] = $this->language->get('code');
			$this->data['direction'] = $this->language->get('direction');

			$this->data['heading_title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));

			$this->data['text_response'] = $this->language->get('text_response');
			$this->data['text_return'] = $this->language->get('text_success');
			$this->data['text_return_wait'] = sprintf($this->language->get('text_success_wait'), HTTPS_SERVER . 'index.php?route=checkout/success');

			$this->data['text_failure'] = $this->language->get('text_failure');
			$this->data['text_failure_wait'] = $this->language->get('text_failure_wait');

			$this->load->model('checkout/order');

			$this->model_checkout_order->confirm($this->request->get['pmt_id'], $this->config->get('emaksut_order_status_delayed_id'));

			$message = '';

			$this->model_checkout_order->update($this->request->get['pmt_id'], $this->config->get('emaksut_order_status_delayed_id'), $message, false);

			$this->data['continue'] = HTTPS_SERVER . 'index.php?route=checkout/success';
			$this->data['meta_redirect'] = '<meta http-equiv="refresh" content="5;url=' . $this->data['continue'] . '">';

			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/emaksut_return.tpl')) {
				$this->template = $this->config->get('config_template') . '/template/payment/emaksut_return.tpl';
			} else {
				$this->template = 'default/template/payment/emaksut_return.tpl';
			}

	  			$this->response->setOutput($this->render(true), $this->config->get('config_compression'));
		} else {
			$this->data['continue'] = HTTPS_SERVER . 'index.php?route=checkout/cart';
			$this->data['text_response_failed'] = $this->language->get('text_response');
			$this->data['text_return'] = $this->language->get('text_failure')."<br />" . $error;
			$this->data['text_return_wait'] = sprintf($this->language->get('text_failure_wait'), HTTPS_SERVER . 'index.php?route=checkout/cart');

			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/emaksut_return.tpl')) {
				$this->template = $this->config->get('config_template') . '/template/payment/emaksut_return.tpl';
			} else {
				$this->template = 'default/template/payment/emaksut_return.tpl';
			}

			$this->response->setOutput($this->render(true), $this->config->get('config_compression'));
		}
	}

	public function callbackcancel() {

		$this->language->load('payment/emaksut');

		$this->data['heading_title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));

		$error = '';
		if (!isset($this->request->get['pmt_id'])) {
			$error .= 'Ei maksutunnusta';
		}

		$this->data['continue'] = HTTPS_SERVER . 'index.php?route=checkout/cart';
		$this->data['text_response'] = '';
		$this->data['text_return'] = $this->language->get('text_cancel')."<br/><br />" . ($error ? $this->language->get('text_failure_error_title') . ' ' . $error : '');
		$this->data['text_return_wait'] = sprintf($this->language->get('text_failure_link'), HTTPS_SERVER . 'index.php?route=checkout/cart');

		$this->data['meta_redirect'] = '';

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/emaksut_return.tpl')) {
			$this->template = $this->config->get('config_template') . '/template/payment/emaksut_return.tpl';
		} else {
			$this->template = 'default/template/payment/emaksut_return.tpl';
		}

		$this->response->setOutput($this->render(true), $this->config->get('config_compression'));
	}

	public function callbackerror() {

		$this->language->load('payment/emaksut');

		$this->data['heading_title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));

		$error = '';
		if (!isset($this->request->get['pmt_id'])) {
			$error .= 'Ei maksutunnusta';
		}

		$this->data['continue'] = HTTPS_SERVER . 'index.php?route=checkout/cart';
		$this->data['text_response'] = '';
		$this->data['text_return'] = $this->language->get('text_failure')."<br/><br />" . ($error ? $this->language->get('text_failure_error_title') . ' ' . $error : '');
		$this->data['text_return_wait'] = sprintf($this->language->get('text_failure_link'), HTTPS_SERVER . 'index.php?route=checkout/cart');

		$this->data['meta_redirect'] = '';

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/emaksut_return.tpl')) {
			$this->template = $this->config->get('config_template') . '/template/payment/emaksut_return.tpl';
		} else {
			$this->template = 'default/template/payment/emaksut_return.tpl';
		}

		$this->response->setOutput($this->render(true), $this->config->get('config_compression'));
	}
}
