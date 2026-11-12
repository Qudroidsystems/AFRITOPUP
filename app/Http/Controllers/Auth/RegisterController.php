<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone_number' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'referral' => ['nullable', 'string', 'max:20'],
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    /** Back to what they were doing (e.g. an order from the website's quick-pay box). */
    protected function registered(\Illuminate\Http\Request $request, $user)
    {
        return redirect()->intended($this->redirectPath());
    }

    /** Customers sign themselves up; they get the Customer role and a wallet straight away. */
    protected function create(array $data)
    {
        $referrer = !empty($data['referral']) ? User::where('referral_code', strtoupper(trim($data['referral'])))->first() : null;

        $user = User::create([
            'name'          => $data['name'],
            'email'         => strtolower($data['email']),
            'phone_number'  => $data['phone_number'],
            'user_type'     => User::TYPE_CUSTOMER,
            'country_code'  => config('afritopup.default_country', 'NG'),
            'referred_by'   => $referrer?->id,
            'referral_code' => strtoupper(\Illuminate\Support\Str::random(8)),
            'password'      => Hash::make($data['password']),
        ]);
        try {
            $user->assignRole('Customer');
        } catch (\Throwable $e) {
            // Roles not seeded yet.
        }
        $user->wallet();

        return $user;
    }
}
