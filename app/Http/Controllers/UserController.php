<?php

namespace App\Http\Controllers;

use App\Models\GeneralSetting;
use App\Models\BookedProperty;
use App\Models\Review;
use App\Models\SupportTicket;
use App\Models\Property; // Ekledim
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public $activeTemplate;

    public function __construct()
    {
        $this->activeTemplate = activeTemplate();
    }

    public function home()
    {
        $pageTitle = 'Dashboard';
        $emptyMessage = 'No booking history found';
        $widget['total_tickets'] = SupportTicket::where('user_id', Auth::id())->count();
        $widget['total_booked'] = BookedProperty::where('user_id', Auth::id())->where('status', 1)->count();
        $widget['total_pending_review'] = Property::with('bookedProperties')
            ->whereHas('bookedProperties', function ($bookedProperty) {
                $bookedProperty->where('user_id', Auth::id())->where('status', 1);
            })->whereDoesntHave('review')->count();
        $propertyBookings = BookedProperty::with('property', 'bookedRooms.room')->where('user_id', Auth::id())->orderBy('id', 'DESC')->limit(6)->get();

        return view($this->activeTemplate . 'user.dashboard', compact('pageTitle', 'emptyMessage', 'propertyBookings', 'widget'));
    }

    public function profile()
    {
        $pageTitle = "Profile Setting";
        $user = Auth::user();
        return view($this->activeTemplate . 'user.profile_setting', compact('pageTitle', 'user'));
    }

    public function submitProfile(Request $request)
    {
        $request->validate([
            'firstname' => 'required|string|max:50',
            'lastname' => 'required|string|max:50',
            'address' => 'sometimes|required|max:80',
            'state' => 'sometimes|required|max:80',
            'zip' => 'sometimes|required|max:40',
            'city' => 'sometimes|required|max:50',
        ], [
            'firstname.required' => 'First name field is required',
            'lastname.required' => 'Last name field is required'
        ]);

        $user = Auth::user();

        $in['firstname'] = $request->firstname;
        $in['lastname'] = $request->lastname;

        $in['address'] = [
            'address' => $request->address,
            'state' => $request->state,
            'zip' => $request->zip,
            'country' => @$user->address->country,
            'city' => $request->city,
        ];

        $notify[] = ['success', 'Profile updated successfully.'];
        return back()->withNotify($notify);
    }

    public function changePassword()
    {
        $pageTitle = 'Change password';
        return view($this->activeTemplate . 'user.password', compact('pageTitle'));
    }

    public function submitPassword(Request $request)
    {
        $password_validation = Password::min(6);
        $general = GeneralSetting::first();
        if ($general->secure_password) {
            $password_validation = $password_validation->mixedCase()->numbers()->symbols()->uncompromised();
        }

        $request->validate([
            'current_password' => 'required',
            'password' => ['required', 'confirmed', $password_validation],
        ]);

        try {
            $user = Auth::user();
            if (!Hash::check($request->current_password, $user->password)) {
                $notify[] = ['error', 'Current password does not match.'];
                return back()->withNotify($notify);
            }

            $user->password = Hash::make($request->password);
            $user->save();
            $notify[] = ['success', 'Password changed successfully.'];
            return back()->withNotify($notify);
        } catch (\Throwable $th) {
            $notify[] = ['error', 'Failed to change password.'];
            return back()->withNotify($notify);
        }
    }
}