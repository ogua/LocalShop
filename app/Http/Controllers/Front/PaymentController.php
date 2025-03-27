<?php


// Using Omnipay PayPal package    "composer require league/omnipay omnipay/paypal"    :https://github.com/thephpleague/omnipay-paypal.    // https://github.com/thephpleague/omnipay    
namespace App\Http\Controllers\Front;
use Paystack;
use Omnipay\Omnipay;
use App\Models\Order;
use App\NotificationService;

use Illuminate\Http\Request;
use App\Models\ProductsAttribute;
use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;


class PaymentController extends Controller
{
    
    public function paymentsuccess() {

        if (request()->has(['trxref', 'reference'])) {

            $paymentDetails = Paystack::getPaymentData();

            //dd($paymentDetails);

            $paymentdata = $paymentDetails['data'];

            $amount = number_format((int) $paymentdata['amount'] / 100, 2);

            // Insert the payment details into our `payments` table
            $payment = new \App\Models\Payment;

            $payment->order_id       = Session::get('order_id'); // 'user_id' was stored in Session inside checkout() method in Front/ProductsController.php    // Interacting With The Session: Retrieving Data: https://laravel.com/docs/9.x/session#retrieving-data    // Comes from our website
            $payment->user_id        = Auth::user()->id; // Retrieving The Authenticated User: https://laravel.com/docs/9.x/authentication#retrieving-the-authenticated-user    // Comes from our website
            $payment->payment_id     = $paymentdata['reference']; // Comes from PayPal website (i.e. API / backend)    // Comes from PayPal website (i.e. API / backend)
            $payment->payer_id       = Auth::user()->id;    // Comes from PayPal website (i.e. API / backend)
            $payment->payer_email    = $paymentdata['customer']['email'];       // Comes from PayPal website (i.e. API / backend)
            $payment->amount         = $amount; // Comes from PayPal website (i.e. API / backend)
            $payment->currency       = 'GHC'; // We get our chosen "PayPal Currency" from our project's .env file using the env() method    // env(): https://laravel.com/docs/9.x/helpers#method-env    // Comes from our website
            $payment->payment_status = $paymentDetails['status']; // Comes from PayPal website (i.e. API / backend)

            //check if exist
            $check = \App\Models\Payment::where('payment_id', $paymentdata['reference'])->first();
            if(!$check){
                $payment->save();

                // Update the `order_status` column in `orders` table with 'Paid'    
            $order_id = Session::get('order_id'); // Interacting With The Session: Retrieving Data: https://laravel.com/docs/9.x/session#retrieving-data
            Order::where('id', $order_id)->update(['order_status' => 'Paid']);


             // Send making the order PayPal payment confirmation email to the user    
             $orderDetails = Order::with('orders_products')->where('id', $order_id)->first()->toArray(); // Eager Loading: https://laravel.com/docs/9.x/eloquent-relationships#eager-loading    // 'orders_products' is the relationship method name in Order.php model
             $email = Auth::user()->email; // Retrieving The Authenticated User: https://laravel.com/docs/9.x/authentication#retrieving-the-authenticated-user

            // Sending the SMS to the user
            NotificationService::send('Dear ' . Auth::user()->name . ', your order has been successfully placed. Your order ID is ' . $order_id . '. Thank you for shopping with us.', Auth::user()->mobile);

            // Inventory Management - Reduce inventory/stock when an order gets placed
                // We wrote the Inventory/Stock Management script in TWO places: in the checkout() method in Front/ProductsController.php and in the success() method in Front/PaypalController.php
                foreach ($orderDetails['orders_products'] as $key => $order) {
                    $getProductStock = ProductsAttribute::getProductStock($order['product_id'], $order['product_size']); // Get the `stock` of that product `product_id` with that specific `size` from `products_attributes` table

                    $newStock = $getProductStock - $order['product_qty']; // The new product `stock` is the original stock reduced by the order `quantity`

                    ProductsAttribute::where([ // Update the new `quantity` in the `products_attributes` table
                        'product_id' => $order['product_id'],
                        'size'       => $order['product_size']
                    ])->update(['stock' => $newStock]);
                }
            }
                // We empty the Cart after making the PayPal payment
                \App\Models\Cart::where('user_id', Auth::user()->id)->delete(); // Retrieving The Authenticated User: https://laravel.com/docs/9.x/authentication#retrieving-the-authenticated-user

                // Redirect the user to the front/products/success.blade.php page
                return view('front.paypal.success');
        }
    }

    
    public function error() {
        // return 'User declined the payment';

        
        return view('front.paypal.fail');
    }



    // PayPal payment gateway integration in Laravel (this route is accessed from checkout() method in Front/ProductsController.php). Rendering front/paypal/paypal.blade.php page
    public function paypal() {
        if (Session::has('order_id')) { // if there's an order has been placed (and got redirected from inside the checkout() method inside Front/ProductsController.php)    // 'user_id' was stored in Session inside checkout() method in Front/ProductsController.php
            return view('front.paypal.paypal');

        } else { // if there's no order has been placed
            return redirect('cart'); // redirect user to cart.blade.php page
        }
    }

}