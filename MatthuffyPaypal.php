<?php
namespace matthuffy\MatthuffyPaypal;
use yii\helpers\Url;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\PayerInfo;
use PayPal\Api\Plan;
use PayPal\Api\ShippingAddress;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use PayPal\Api\CreditCard;
use PayPal\Api\CreditCardToken;
use PayPal\Api\FundingInstrument;
use PayPal\Api\Agreement;
use PayPal\Api\AgreementStateDescriptor;
use PayPal\Api\ChargeModel;
use PayPal\Api\Currency;
use PayPal\Api\MerchantPreferences;
use PayPal\Api\PaymentDefinition;
use PayPal\Api\Patch;
use PayPal\Api\PatchRequest;
use PayPal\Common\PayPalModel;
use PayPal\Api\ExecutePayment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\Payout;
use PayPal\Api\PayoutSenderBatchHeader;


date_default_timezone_set(@date_default_timezone_get());


class MatthuffyPaypal {
	
	#PayPal global creds
	protected $clientId;
	protected $clientSecret;
	
	#store the values of the recurring payments in an array
	protected $RecurringDetails;
	
	#set agreement urls
	protected $agreementSucess;
	protected $agreementCancelled;
	
	protected $environment;
	

	public function setPPcreds($clientId, $clientSecret, $environment, $agreementSucess, $agreementCancelled) {
  		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
		$this->environment = $environment;
		$this->agreementSucess = $agreementSucess;
		$this->agreementCancelled = $agreementCancelled;
 	}
	public function setRecurring($RecurringDetails) {
  		$this->RecurringDetails = $RecurringDetails;
		
 	}
	
	private function getApiContext($clientId, $clientSecret)
	{

		$apiContext = new ApiContext(
			new OAuthTokenCredential(
				$clientId,
				$clientSecret
			)
		);
	
		$apiContext->setConfig(
			array(
				'mode' => $this->environment,
				'log.LogEnabled' => true,
				'log.FileName' => 'PayPal.log',
				'log.LogLevel' => 'DEBUG', // PLEASE USE `INFO` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS
				'cache.enabled' => false,
			   
			)
		);
		
		return $apiContext;
		
	}
	#End of Paypal Global
	

	public function ccvault($CardDetails)
	{
		$CardDetails[] = array($CardDetails);
		#get the paypal details again
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		#do the credit card save to vault
		$creditCard = new \PayPal\Api\CreditCard();
		$creditCard->setType($CardDetails['Type'])
			->setNumber($CardDetails['cardNumber'])
			->setExpireMonth($CardDetails['expMonth'])
			->setExpireYear($CardDetails['expYear'])
			->setCvv2($CardDetails['ccV2'])
			->setFirstName($CardDetails['cardFname'])
			->setLastName($CardDetails['cardLname']);
			
		try {
    		$creditCard->create($apiContext);
   			 return $creditCard->getID();
			}
		catch (\PayPal\Exception\PayPalConnectionException $ex) {
			// This will print the detailed information on the exception. 
			//REALLY HELPFUL FOR DEBUGGING
    		return $ex->getData();
			}
	}
	
	public function paypalPayment($purchaseDetails)
	{
		$purchaseDetails[] = array($purchaseDetails);
		#get the paypal details again
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		
		$payer = new Payer();
		$payer->setPaymentMethod("paypal");
		
		// ### Itemized information
		// (Optional) Lets you specify item wise
		// information
		$item1 = new Item();
		$item1->setName($purchaseDetails['itemName'])
			->setCurrency($purchaseDetails['currency'])
			->setQuantity($purchaseDetails['quantity'])
			->setSku($purchaseDetails['SKU']) // Similar to `item_number` in Classic API
			->setPrice($purchaseDetails['price']);
	
		
		
		$itemList = new ItemList();
		$itemList->setItems(array($item1));
		
		// ### Additional payment details
		// Use this optional field to set additional
		// payment information such as tax, shipping
		// charges etc.
		$details = new Details();
		$details->setShipping($purchaseDetails['shipping'])
			->setTax($purchaseDetails['tax'])
			->setSubtotal($purchaseDetails['subtotal']);
		
		// ### Amount
		// Lets you specify a payment amount.
		// You can also specify additional details
		// such as shipping, tax.
		$amount = new Amount();
		$amount->setCurrency($purchaseDetails['currency'])
			->setTotal($purchaseDetails['total'])
			->setDetails($details);
		
		// ### Transaction
		// A transaction defines the contract of a
		// payment - what is the payment for and who
		// is fulfilling it. 
		$transaction = new Transaction();
		$transaction->setAmount($amount)
			->setItemList($itemList)
			->setDescription($purchaseDetails['description'])
			->setInvoiceNumber(uniqid());
		
		// ### Redirect urls
		// Set the urls that the buyer must be redirected to after 
		// payment approval/ cancellation.
		//$baseUrl = getBaseUrl();
		$baseUrl = Url::home(true);
		$redirectUrls = new RedirectUrls();
		$redirectUrls->setReturnUrl("$baseUrl/check-out/executepayment.php?success=true")
			->setCancelUrl("$baseUrl/check-out/ExecutePayment.php?success=false");
		
		// ### Payment
		// A Payment Resource; create one using
		// the above types and intent set to 'sale'
		$payment = new Payment();
		$payment->setIntent("sale")
			->setPayer($payer)
			->setRedirectUrls($redirectUrls)
			->setTransactions(array($transaction));
		
		
		// For Sample Purposes Only.
		$request = clone $payment;
		
		// ### Create Payment
		// Create a payment by calling the 'create' method
		// passing it a valid apiContext.
		// (See bootstrap.php for more on `ApiContext`)
		// The return object contains the state and the
		// url to which the buyer must be redirected to
		// for payment approval
		try {
			$payment->create($apiContext);
		} catch (Exception $ex) {
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			echo "ERROR.", "Payment", null, $request, $ex;
			exit(1);
		}
		
		// ### Get redirect url
		// The API response provides the url that you must redirect
		// the buyer to. Retrieve the url from the $payment->getApprovalLink()
		// method
		$approvalUrl = $payment->getApprovalLink();
		
		// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
		 //echo $approvalUrl;
		
		return $payment;

	}
	public function executePayment($success, $paymentId, $payerID, $purchaseDetails)
	{
		// ### Approval Status
		// Determine if the user approved the payment or not
		if (isset($success) && $success == 'true') {
		
			$purchaseDetails[] = array($purchaseDetails);
			#get the paypal details again
			$clientSecret = $this->clientSecret;
			$clientId = $this->clientId;
			// Get the payment Object by passing paymentId
			// payment id was previously stored in session in
			// CreatePaymentUsingPayPal.php
			$paymentId = $paymentId;
			$payment = Payment::get($paymentId, $apiContext);
		
			// ### Payment Execute
			// PaymentExecution object includes information necessary
			// to execute a PayPal account payment.
			// The payer_id is added to the request query parameters
			// when the user is redirected from paypal back to your site
			$execution = new PaymentExecution();
			$execution->setPayerId($payerID);
		
			// ### Optional Changes to Amount
			// If you wish to update the amount that you wish to charge the customer,
			// based on the shipping address or any other reason, you could
			// do that by passing the transaction object with just `amount` field in it.
			// Here is the example on how we changed the shipping to $1 more than before.
			$transaction = new Transaction();
			$details = new Details();
			$details->setShipping($purchaseDetails['shipping'])
			->setTax($purchaseDetails['tax'])
			->setSubtotal($purchaseDetails['subtotal']);
		
		// ### Amount
		// Lets you specify a payment amount.
		// You can also specify additional details
		// such as shipping, tax.
			$amount = new Amount();
			$amount->setCurrency($purchaseDetails['currency'])
				->setTotal($purchaseDetails['total'])
				->setDetails($details);
				$transaction->setAmount($amount);
			
				// Add the above transaction object inside our Execution object.
				$execution->addTransaction($transaction);
		
			try {
				// Execute the payment
				// (See bootstrap.php for more on `ApiContext`)
				$result = $payment->execute($execution, $apiContext);
		
				// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
				//ResultPrinter::printResult("Executed Payment", "Payment", $payment->getId(), $execution, $result);
		
				try {
					$payment = Payment::get($paymentId, $apiContext);
				} catch (Exception $ex) {
					// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
					//ResultPrinter::printError("Get Payment", "Payment", null, null, $ex);
					exit(1);
				}
			} catch (Exception $ex) {
				// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
				//ResultPrinter::printError("Executed Payment", "Payment", null, null, $ex);
				exit(1);
			}
		
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			
		
			return $payment;
			} else {
				// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
				
				exit;
			}

	}
	public function paypalPaymentCard($purchaseDetails, $CardDetails)
	{
		$purchaseDetails[] = array($purchaseDetails);
		$CardDetails[] = array($CardDetails);
		
		#get the paypal details again
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
				
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		$card = new CreditCard();
		$card->setType($CardDetails['Type'])
			->setNumber($CardDetails['cardNumber'])
			->setExpireMonth($CardDetails['expMonth'])
			->setExpireYear($CardDetails['expYear'])
			->setCvv2($CardDetails['ccV2'])
			->setFirstName($CardDetails['cardFname'])
			->setLastName($CardDetails['cardLname']);
		
		// ### FundingInstrument
		// A resource representing a Payer's funding instrument.
		// For direct credit card payments, set the CreditCard
		// field on this object.
		$fi = new FundingInstrument();
		$fi->setCreditCard($card);
		
		// ### Payer
		// A resource representing a Payer that funds a payment
		// For direct credit card payments, set payment method
		// to 'credit_card' and add an array of funding instruments.
		$payer = new Payer();
		$payer->setPaymentMethod("credit_card")
			->setFundingInstruments(array($fi));
		
		// ### Itemized information
		// (Optional) Lets you specify item wise
		// information
		$item1 = new Item();
		$item1->setName($purchaseDetails['itemName'])
			->setCurrency($purchaseDetails['currency'])
			->setQuantity($purchaseDetails['quantity'])
			->setSku($purchaseDetails['SKU']) // Similar to `item_number` in Classic API
			->setPrice($purchaseDetails['price']);
	
		
		
		$itemList = new ItemList();
		$itemList->setItems(array($item1));
		
		// ### Additional payment details
		// Use this optional field to set additional
		// payment information such as tax, shipping
		// charges etc.
		$details = new Details();
		$details->setShipping($purchaseDetails['shipping'])
			->setTax($purchaseDetails['tax'])
			->setSubtotal($purchaseDetails['subtotal']);
		
		// ### Amount
		// Lets you specify a payment amount.
		// You can also specify additional details
		// such as shipping, tax.
		$amount = new Amount();
		$amount->setCurrency($purchaseDetails['currency'])
			->setTotal($purchaseDetails['total'])
			->setDetails($details);
		
		// ### Transaction
		// A transaction defines the contract of a
		// payment - what is the payment for and who
		// is fulfilling it. 
		$transaction = new Transaction();
		$transaction->setAmount($amount)
			->setItemList($itemList)
			->setDescription($purchaseDetails['description'])
			->setInvoiceNumber(uniqid());
		
		// ### Payment
		// A Payment Resource; create one using
		// the above types and intent set to sale 'sale'
		$payment = new Payment();
		$payment->setIntent("sale")
			->setPayer($payer)
			->setTransactions(array($transaction));
		
		// For Sample Purposes Only.
		$request = clone $payment;
		
		// ### Create Payment
		// Create a payment by calling the payment->create() method
		// with a valid ApiContext (See bootstrap.php for more on `ApiContext`)
		// The return object contains the state.
		try {
			$payment->create($apiContext);
		} catch (Exception $ex) {
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			//echo 'Create Payment Using Credit Card. If 500 Exception, try creating a new Credit Card using <a href="https://ppmts.custhelp.com/app/answers/detail/a_id/750">Step 4, on this link</a>, and using it.', 'Payment', null, $request, $ex;
			exit(1);
		}
		
		// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
		 echo $payment;
		
		return $payment;

	}
	
	public function paymentSavedCard($purchaseDetails, $cardID)
	{
		#get the paypal details again
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		$cardID = $cardID;
		$purchaseDetails[] = array($purchaseDetails);
		
		#get the paypal details again
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		// ### Credit card token
		// Saved credit card id from a previous call to
		// CreateCreditCard.php
		$creditCardToken = new CreditCardToken();
		$creditCardToken->setCreditCardId($cardID);
		
		// ### FundingInstrument
		// A resource representing a Payer's funding instrument.
		// For stored credit card payments, set the CreditCardToken
		// field on this object.
		$fi = new FundingInstrument();
		$fi->setCreditCardToken($creditCardToken);
		
		// ### Payer
		// A resource representing a Payer that funds a payment
		// For stored credit card payments, set payment method
		// to 'credit_card'.
		$payer = new Payer();
		$payer->setPaymentMethod("credit_card")
			->setFundingInstruments(array($fi));
		
		// ### Itemized information
		// (Optional) Lets you specify item wise
		// information
		$item1 = new Item();
		$item1->setName($purchaseDetails['itemName'])
			->setCurrency($purchaseDetails['currency'])
			->setQuantity($purchaseDetails['quantity'])
			->setSku($purchaseDetails['SKU']) // Similar to `item_number` in Classic API
			->setPrice($purchaseDetails['price']);
		
		
		
		$itemList = new ItemList();
		$itemList->setItems(array($item1));
		
		// ### Additional payment details
		// Use this optional field to set additional
		// payment information such as tax, shipping
		// charges etc.
		$details = new Details();
		$details->setShipping($purchaseDetails['shipping'])
			->setTax($purchaseDetails['tax'])
			->setSubtotal($purchaseDetails['subtotal']);
		
		// ### Amount
		// Lets you specify a payment amount.
		// You can also specify additional details
		// such as shipping, tax.
		$amount = new Amount();
		$amount->setCurrency($purchaseDetails['currency'])
			->setTotal($purchaseDetails['total'])
			->setDetails($details);
		
		// ### Transaction
		// A transaction defines the contract of a
		// payment - what is the payment for and who
		// is fulfilling it. 
		$transaction = new Transaction();
		$transaction->setAmount($amount)
			->setItemList($itemList)
			->setDescription($purchaseDetails['description'])
			->setInvoiceNumber(uniqid());
		
		// ### Payment
		// A Payment Resource; create one using
		// the above types and intent set to 'sale'
		$payment = new Payment();
		$payment->setIntent("sale")
			->setPayer($payer)
			->setTransactions(array($transaction));
		
		
		// For Sample Purposes Only.
		//$request = clone $payment;
		
		// ###Create Payment
		// Create a payment by calling the 'create' method
		// passing it a valid apiContext.
		// (See bootstrap.php for more on `ApiContext`)
		// The return object contains the state.
		try {
			$payment->create($apiContext);
		} catch (Exception $ex) {
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			//echo "Create Payment using Saved Card", "Payment", null, $request, $ex;
			exit(1);
		}
		
		// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
		 //echo "Create Payment using Saved Card", "Payment", $payment->getId(), $request, $payment;
		
		return $payment;

	}
	public function UpdatePlan()
	{
		$createdPlan = $this->createPlan();
		
		#get the paypal details again
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		try {
			$patch = new Patch();
		
			$value = new PayPalModel('{
				   "state":"ACTIVE"
				 }');
		
			$patch->setOp('replace')
				->setPath('/')
				->setValue($value);
			$patchRequest = new PatchRequest();
			$patchRequest->addPatch($patch);
		
			$createdPlan->update($patchRequest, $apiContext);
		
			$plan = Plan::get($createdPlan->getId(), $apiContext);
		} catch (Exception $ex) {
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			//echo "Updated the Plan to Active State", "Plan", null, $patchRequest, $ex;
			exit(1);
		}
		
		return $plan;

	}
	public function createPlan()
	{
		#get the recurring details from the global
		
		#get the paypal details again
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		// Create a new instance of Plan object
		$plan = new Plan();
		
		// # Basic Information
		// Fill up the basic information that is required for the plan
		$plan->setName($this->RecurringDetails['planName'])
			->setDescription($this->RecurringDetails['description'])
			->setType('fixed');
		
		// # Payment definitions for this billing plan.
		$paymentDefinition = new PaymentDefinition();
		
		// The possible values for such setters are mentioned in the setter method documentation.
		// Just open the class file. e.g. lib/PayPal/Api/PaymentDefinition.php and look for setFrequency method.
		// You should be able to see the acceptable values in the comments.
		$paymentDefinition->setName($this->RecurringDetails['setName'])
			->setType($this->RecurringDetails['setType'])
			->setFrequency($this->RecurringDetails['frequency'])
			->setFrequencyInterval($this->RecurringDetails['FrequencyInterval'])
			->setCycles($this->RecurringDetails['Cycles'])
			->setAmount(new Currency(array('value' => $this->RecurringDetails['Amount'], 'currency' => $this->RecurringDetails['currency'])));
		
		// Charge Models Maybe why it is duplicating ??
		//$chargeModel = new ChargeModel();
		//$chargeModel->setType($this->RecurringDetails['chargeModel'])
			//->setAmount(new Currency(array('value' => $this->RecurringDetails['Amount'], 'currency' => $this->RecurringDetails['currency'])));
		
		//$paymentDefinition->setChargeModels(array($chargeModel));
		
		$merchantPreferences = new MerchantPreferences();
		
		// ReturnURL and CancelURL are not required and used when creating billing agreement with payment_method as "credit_card".
		// However, it is generally a good idea to set these values, in case you plan to create billing agreements which accepts "paypal" as payment_method.
		// This will keep your plan compatible with both the possible scenarios on how it is being used in agreement.
		
		$merchantPreferences->setReturnUrl($this->agreementSucess)
			->setCancelUrl($this->agreementCancelled)
			->setAutoBillAmount("yes")
			->setInitialFailAmountAction($this->RecurringDetails['FailAmountAction'])
			->setMaxFailAttempts($this->RecurringDetails['MaxFailAttempts'])
			->setSetupFee(new Currency(array('value' => $this->RecurringDetails['SetupFee'], 'currency' => $this->RecurringDetails['currency'])));
		
		
		$plan->setPaymentDefinitions(array($paymentDefinition));
		$plan->setMerchantPreferences($merchantPreferences);
		
		// For Sample Purposes Only.
		$request = clone $plan;
		
		// ### Create Plan
		try {
			$output = $plan->create($apiContext);
		} catch (Exception $ex) {
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			//echo "Created Plan", "Plan", null, $request, $ex;
			
			exit(1);
		}
		
		// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
		 //echo "Created Plan", "Plan", $output->getId(), $request, $output;
		
		return $output;

	}
	public function BillingwithPayPal($RecurringDetails)
	{
		$createdPlan = $this->UpdatePlan();
		$RecurringDetails[] = array($RecurringDetails);
		
		#set the date and time of today

		#get the paypal details again
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		$agreement = new Agreement();
		
		$agreement->setName('Base Agreement')
			->setDescription($RecurringDetails['description'])
			->setStartDate($RecurringDetails['StartDate']);
		
		// Add Plan ID
		// Please note that the plan Id should be only set in this case.
		$plan = new Plan();
		$plan->setId($createdPlan->getId());
		$agreement->setPlan($plan);
		
		// Add Payer
		$payer = new Payer();
		$payer->setPaymentMethod('paypal');
		$agreement->setPayer($payer);
		
		// Add Shipping Address
		/*$shippingAddress = new ShippingAddress();
		$shippingAddress->setLine1('111 First Street')
			->setCity('Saratoga')
			->setState('CA')
			->setPostalCode('95070')
			->setCountryCode('US');
		$agreement->setShippingAddress($shippingAddress);*/
		
		// For Sample Purposes Only.
		$request = clone $agreement;
		
		// ### Create Agreement
		try {
			// Please note that as the agreement has not yet activated, we wont be receiving the ID just yet.
			$agreement = $agreement->create($apiContext);
		
			// ### Get redirect url
			// The API response provides the url that you must redirect
			// the buyer to. Retrieve the url from the $agreement->getApprovalLink()
			// method
			$approvalUrl = $agreement->getApprovalLink();
			
		} catch (Exception $ex) {
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			echo "Created Billing Agreement.", "Agreement", null, $request, $ex;
			exit(1);
		}
		
		// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
		// echo "Created Billing Agreement. Please visit the URL to Approve.", "Agreement", "<a href='$approvalUrl' >$approvalUrl</a>", $request, $agreement;
		
		return $agreement;

	}
	
	public function BillingwithCard($CardDetails, $RecurringDetails)
	{
		$createdPlan = $this->UpdatePlan();
		$CardDetails[] = array($CardDetails);
		$RecurringDetails[] = array($RecurringDetails);
		
		#store the recurring details for later in a global
		
		
		#set the date and time of today
		#set the date and time of today
		

		#get the paypal details again
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		$agreement = new Agreement();

		$agreement->setName('DPRP')
			->setDescription($RecurringDetails['description'])
			->setStartDate($RecurringDetails['StartDate']);
		
		// Add Plan ID
		// Please note that the plan Id should be only set in this case.
		$plan = new Plan();
		$plan->setId($createdPlan->getId());
		$agreement->setPlan($plan);
		
		// Add Payer
		$payer = new Payer();
		$payer->setPaymentMethod('credit_card')
			->setPayerInfo(new PayerInfo(array('email' => $CardDetails['Email'])));
		
		// Add Credit Card to Funding Instruments
		$creditCard = new CreditCard();
					
			$creditCard->setType($CardDetails['Type'])
			->setNumber($CardDetails['cardNumber'])
			->setExpireMonth($CardDetails['expMonth'])
			->setExpireYear($CardDetails['expYear'])
			->setCvv2($CardDetails['ccV2'])
			->setFirstName($CardDetails['cardFname'])
			->setLastName($CardDetails['cardLname']);
		
		
		$fundingInstrument = new FundingInstrument();
		$fundingInstrument->setCreditCard($creditCard);
		$payer->setFundingInstruments(array($fundingInstrument));
		//Add Payer to Agreement
		$agreement->setPayer($payer);
		
		/*// Add Shipping Address
		$shippingAddress = new ShippingAddress();
		$shippingAddress->setLine1('111 First Street')
			->setCity('Saratoga')
			->setState('CA')
			->setPostalCode('95070')
			->setCountryCode('US');
		$agreement->setShippingAddress($shippingAddress);*/
		
		// For Sample Purposes Only.
		
		
		// ### Create Agreement
		try {
			// Please note that as the agreement has not yet activated, we wont be receiving the ID just yet.
			$agreement = $agreement->create($apiContext);
			$creditCard->create($apiContext);
		} catch (Exception $ex) {
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			//echo "Created Billing Agreement.", "Agreement", $agreement->getId(), $request, $ex;
			$error_object = json_decode($ex->getData());
		switch ($error_object->name)
		{
			case 'VALIDATION_ERROR':
				echo "Payment failed due to invalid Credit Card details:\n";
				foreach ($error_object->details as $e)
				{
					echo $e->issue;
				}
				break;
		}
			
			exit(1);
		}
		
		 // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
		 //echo "Created Billing Agreement.", "Agreement", $agreement->getId(), $request, $agreement;
		
	
		
		$agreement = json_encode(array_merge_recursive(json_decode($agreement, true),json_decode($creditCard, true)));
		//$agreement = array_combine($agreement, $creditCard);
		//$agreement = my_merge( $agreement, $creditCard );
		
		//$agreement = json_encode( $agreement );
		return $agreement;

		
	}
	public function ExecuteAgreement($token)
	{
		#get the paypal details again
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		if ($token) {
		$token = $token;
			$agreement = new \PayPal\Api\Agreement();
			try {
				// ## Execute Agreement
				// Execute the agreement by passing in the token
				$agreement->execute($token, $apiContext);
			} catch (Exception $ex) {
				// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
				//echo "1 Executed an Agreement", "Agreement", $agreement->getId(), $token, $ex;
				exit(1);
			}
		
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			//echo "2 Executed an Agreement", "Agreement", $agreement->getId(), $token, $agreement;
		
			// ## Get Agreement
			// Make a get call to retrieve the executed agreement details
			try {
				$agreement = \PayPal\Api\Agreement::get($agreement->getId(), $apiContext);
			} catch (Exception $ex) {
				// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
				//echo "3 Get Agreement", "Agreement", null, null, $ex;
				exit(1);
			}
		
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			//echo "4 Get Agreement", "Agreement", $agreement->getId(), null, $agreement;
			return $agreement;
		} 
		else {
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			//echo "User Cancelled the Approval", null;
			return 'Cancelled';
		}
	}
	
	public function creditcard_state($savedcard)
	{
			$clientSecret = $this->clientSecret;
			$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		try {
		$card = CreditCard::get($savedcard, $apiContext);
	} catch (Exception $ex) {
		// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
		//ResultPrinter::printError("Get Credit Card", "Credit Card", $card->getId(), null, $ex);
		exit(1);
	}
	
	// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
	 //echo "Get Credit Card", "Credit Card", $card->getId(), null, $card;
	
	return $card;
	
	}
	
	public function update_card($savedcard, $newcard)
	{
		
		#get the paypal details again
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		$card = $this->show_card($savedcard);
		$newcard[] = array($newcard);
		
		$pathOperation = new Patch();
		$pathOperation->setOp("replace")
    	->setPath('/type')
    	->setValue($newcard['Type']);
		
		$pathOperation1 = new Patch();
		$pathOperation1->setOp("replace")
			->setPath('/expire_month')
			->setValue($newcard['expMonth']);
		
		$pathOperation3 = new Patch();
		$pathOperation3->setOp("replace")
    	->setPath('/expire_year')
    	->setValue($newcard['expYear']);
		
		$pathOperation2 = new Patch();
		$pathOperation2->setOp("replace")
    	->setPath('/number')
    	->setValue($newcard['cardNumber']);
		
		$pathOperation4 = new Patch();
		$pathOperation4->setOp("replace")
    	->setPath('/first_name')
    	->setValue($newcard['cardFname']);
		
		$pathOperation5 = new Patch();
		$pathOperation5->setOp("replace")
    	->setPath('/last_name')
    	->setValue($newcard['cardLname']);
		
		$pathOperation6 = new Patch();
		$pathOperation6->setOp("replace")
    	->setPath('/cvv2')
    	->setValue($newcard['ccV2']);
		
		// ### Another Patch Object
		// You could set more than one patch while updating a credit card.
		/*$pathOperation2 = new Patch();
		$pathOperation2->setOp('add')
			->setPath('/billing_address')
			->setValue(json_decode('{
					"line1": "111 First Street",
					"city": "Saratoga",
					"country_code": "US",
					"state": "CA",
					"postal_code": "95070"
				}'));*/
		
		$pathRequest = new \PayPal\Api\PatchRequest();
		$pathRequest->addPatch($pathOperation)
			->addPatch($pathOperation1)
			->addPatch($pathOperation2);
		/// ### Update Credit Card
		// (See bootstrap.php for more on `ApiContext`)
		try {
			$card = $card->update($pathRequest, $apiContext);
		} catch (Exception $ex) {
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			echo "Updated Credit Card", "Credit Card", $card->getId(), $pathRequest, $ex;
			exit(1);
		}
		
		// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
		 echo "Updated Credit Card", "Credit Card", $card->getId(), $pathRequest, $card;
		
		return $card;

	}
	
	public function show_card($savedcard)
	{
		
		#get the paypal details again
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		$mycard = $savedcard;
		/// ### Retrieve card
		// (See bootstrap.php for more on `ApiContext`)
		try {
			$card = CreditCard::get($mycard, $apiContext);
		} catch (Exception $ex) {
			//echo "Get Credit Card", "Credit Card", $mycard, null, $ex;
			exit(1);
		}
		
		//echo "Get Credit Card", "Credit Card", $mycard, null, $card;
		
		return $card;

	}
	
	public function getplan($planID)
	{
		
		#get the paypal details again
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		try {
			$agreement = Agreement::get($planID, $apiContext);
		} catch (Exception $ex) {
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			//echo "ddddRetrieved an Agreement", "Agreement", $agreement->getId(), $createdAgreement->getId(), $ex;
			exit(1);
		}
		
		// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
		//echo "evidently workd Retrieved an Agreement", "Agreement", $agreement->getId(), $createdAgreement->getId(), $agreement;
		
		return $agreement;

	}
	
	public function suspend_agreement($planID)
	{
		
		$createdAgreement = $this->getplan($planID);
		#get the paypal details again
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		//Create an Agreement State Descriptor, explaining the reason to suspend.
		$agreementStateDescriptor = new AgreementStateDescriptor();
		$agreementStateDescriptor->setNote("Suspending the agreement");
		
		try {
			$createdAgreement->suspend($agreementStateDescriptor, $apiContext);
		
			// Lets get the updated Agreement Object
			$agreement = Agreement::get($planID, $apiContext);
		} catch (Exception $ex) {
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			//echo "error Suspended the Agreement", "Agreement", null, $agreementStateDescriptor, $ex;
			exit(1);
		}
		
		// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
		 //echo "yes Suspended the Agreement", "Agreement", $agreement->getId(), $agreementStateDescriptor, $agreement;
		
		return $agreement;

	}
	
	public function reactivate_agreement($planID)
	{
		
		$suspendedAgreement = $this->getplan($planID);
		#get the paypal details again
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		
		$agreementStateDescriptor = new AgreementStateDescriptor();
		$agreementStateDescriptor->setNote("Reactivating the agreement");
		
		try {
			$suspendedAgreement->reActivate($agreementStateDescriptor, $apiContext);
		
			// Lets get the updated Agreement Object
			$agreement = Agreement::get($planID, $apiContext);
		} catch (Exception $ex) {
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			//ResultPrinter::printResult("Reactivate the Agreement", "Agreement", $agreement->getId(), $suspendedAgreement, $ex);
			exit(1);
		}
		
		// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
		//ResultPrinter::printResult("Reactivate the Agreement", "Agreement", $agreement->getId(), $suspendedAgreement, $agreement);
		
		return $agreement;

	}
	
	public function terminate_agreement($planID)
	{
		$suspendedAgreement = $this->getplan($planID);
		#get the paypal details again
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		
		$agreementStateDescriptor = new AgreementStateDescriptor();
		$agreementStateDescriptor->setNote("Cancelling the agreement");
		
		try {
			$suspendedAgreement->cancel($agreementStateDescriptor, $apiContext);
		
			// Lets get the updated Agreement Object
			$agreement = Agreement::get($planID, $apiContext);
		} catch (Exception $ex) {
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			//ResultPrinter::printResult("Reactivate the Agreement", "Agreement", $agreement->getId(), $suspendedAgreement, $ex);
			exit(1);
		}
		
		// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
		//ResultPrinter::printResult("Reactivate the Agreement", "Agreement", $agreement->getId(), $suspendedAgreement, $agreement);
		
		return $agreement;

	}
	
	public function singlepayout($subject, $note, $receiveremail, $value, $currency, $itemid)
	{
		$payouts = new \PayPal\Api\Payout();
		
		$clientSecret = $this->clientSecret;
		$clientId = $this->clientId;
		
		#call the paypal api settings
		$apiContext = $this->getApiContext($clientId,$clientSecret);
		
		$senderBatchHeader = new \PayPal\Api\PayoutSenderBatchHeader();
		// ### NOTE:
		// You can prevent duplicate batches from being processed. If you specify a `sender_batch_id` that was used in the last 30 days, the batch will not be processed. For items, you can specify a `sender_item_id`. If the value for the `sender_item_id` is a duplicate of a payout item that was processed in the last 30 days, the item will not be processed.
		
		// #### Batch Header Instance
		$senderBatchHeader->setSenderBatchId(uniqid())
			->setEmailSubject($subject);
		
		// #### Sender Item
		// Please note that if you are using single payout with sync mode, you can only pass one Item in the request
		$senderItem = new \PayPal\Api\PayoutItem();
		$senderItem->setRecipientType('Email')
			->setNote($note)
			->setReceiver($receiveremail)
			->setSenderItemId($itemid)
			->setAmount(new \PayPal\Api\Currency('{
								"value":"'.$value.'",
								"currency":"'.$currency.'"
							}'));
		
		$payouts->setSenderBatchHeader($senderBatchHeader)
			->addItem($senderItem);
		
		
		// For Sample Purposes Only.
		$request = clone $payouts;
		
		// ### Create Payout
		try {
			$output = $payouts->createSynchronous($apiContext);
		} catch (Exception $ex) {
			// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
			//ResultPrinter::printError("Created Single Synchronous Payout", "Payout", null, $request, $ex);
			exit(1);
		}
		
		// NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
		 //ResultPrinter::printResult("Created Single Synchronous Payout", "Payout", $output->getBatchHeader()->getPayoutBatchId(), $request, $output);
		
		return $output;

	}
}