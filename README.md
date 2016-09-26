# yii2-matthuffy-paypal

composer require matthuffy/yii2-matthuffy-paypal

use matthuffy\MatthuffyPaypal\MatthuffyPaypal;

$paypal = new MatthuffyPaypal();

initiate data

$paypal->setPPcreds($clientId, $clientSecret, $environment, $agreementSucess, $agreementCancelled);

functions:

ccvault($CardDetails)

paypalPayment($purchaseDetails)

executePayment($success, $paymentId, $payerID, $purchaseDetails)

paypalPaymentCard($purchaseDetails, $CardDetails)

paymentSavedCard($purchaseDetails, $cardID)

BillingwithPayPal($RecurringDetails)

BillingwithCard($CardDetails, $RecurringDetails)

ExecuteAgreement($token)

creditcard_state($savedcard)

update_card($savedcard, $newcard)

show_card($savedcard)

getplan($planID)

suspend_agreement($planID)

reactivate_agreement($planID)

terminate_agreement($planID)

singlepayout($subject, $note, $receiveremail, $value, $currency, $itemid)

$paypal->setRecurring($RecurringDetails); to store for the recurring creation.