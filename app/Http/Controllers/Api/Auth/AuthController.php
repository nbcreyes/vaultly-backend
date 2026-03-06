<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Responses\ApiResponse;
use App\Mail\PasswordResetMail;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * AuthController
 *
 * Handles all buyer authentication flows:
 *   - Registration with email verification
 *   - Email verification via token
 *   - Resend verification email
 *   - Login with Sanctum token issuance
 *   - Authenticated user profile
 *   - Logout with token revocation
 *   - Forgot password (send reset email)
 *   - Reset password (consume token and update password)
 */
class AuthController extends Controller
{
    /**
     * Register a new buyer account.
     *
     * Creates the user, generates a verification token,
     * and sends the verification email. The account is
     * created with role=buyer and status=active but
     * email_verified_at remains null until verified.
     *
     * POST /api/v1/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $verificationToken = Str::random(64);

        $user = User::create([
            'name'                       => $request->name,
            'email'                      => $request->email,
            'password'                   => $request->password,
            'role'                       => 'buyer',
            'status'                     => 'active',
            'email_verification_token'   => $verificationToken,
        ]);

        // Build the frontend verification URL
        // The frontend will extract the token and call our verify endpoint
        $verificationUrl = config('app.frontend_url')
            . '/verify-email?token='
            . $verificationToken
            . '&email='
            . urlencode($user->email);

        Mail::to($user->email)->send(new VerifyEmailMail($verificationUrl, $user->name));

        return ApiResponse::created([
            'user' => [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'role'              => $user->role,
                'email_verified'    => false,
            ],
        ], 'Account created. Please check your email to verify your address.');
    }

    /**
     * Verify an email address using the token from the verification email.
     *
     * POST /api/v1/auth/verify-email
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $request->email)
            ->where('email_verification_token', $request->token)
            ->first();

        if (!$user) {
            return ApiResponse::error('Invalid or expired verification link.', 422);
        }

        if ($user->email_verified_at) {
            return ApiResponse::success(null, 'Email address is already verified.');
        }

        $user->update([
            'email_verified_at'          => now(),
            'email_verification_token'   => null,
        ]);

        return ApiResponse::success(null, 'Email address verified successfully. You can now log in.');
    }

    /**
     * Resend the email verification link.
     *
     * POST /api/v1/auth/resend-verification
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::where('email', $request->email)->first();

        // Always return success even if the email is not found to prevent
        // user enumeration attacks
        if (!$user || $user->email_verified_at) {
            return ApiResponse::success(
                null,
                'If that email address is registered and unverified, a new link has been sent.'
            );
        }

        $verificationToken = Str::random(64);

        $user->update(['email_verification_token' => $verificationToken]);

        $verificationUrl = config('app.frontend_url')
            . '/verify-email?token='
            . $verificationToken
            . '&email='
            . urlencode($user->email);

        Mail::to($user->email)->send(new VerifyEmailMail($verificationUrl, $user->name));

        return ApiResponse::success(
            null,
            'If that email address is registered and unverified, a new link has been sent.'
        );
    }

    /**
     * Log in and receive an API token.
     *
     * Validates credentials, checks account status, checks email
     * verification, issues a Sanctum token.
     *
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return ApiResponse::error('The provided credentials are incorrect.', 401);
        }

        if ($user->isBanned()) {
            return ApiResponse::error('Your account has been permanently suspended.', 403);
        }

        if ($user->status === 'suspended') {
            return ApiResponse::error('Your account has been temporarily suspended. Please contact support.', 403);
        }

        if (!$user->email_verified_at) {
            return ApiResponse::error(
                'Please verify your email address before logging in.',
                403,
                ['email_verified' => false]
            );
        }

        // Revoke all existing tokens before issuing a new one
        // This ensures only one active session at a time
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success([
            'token'      => $token,
            'token_type' => 'Bearer',
            'user'       => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'role'           => $user->role,
                'avatar_url'     => $user->avatar_url,
                'email_verified' => true,
            ],
        ], 'Login successful.');
    }

    /**
     * Return the currently authenticated user's profile.
     *
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('sellerProfile');

        $data = [
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'role'           => $user->role,
            'status'         => $user->status,
            'avatar_url'     => $user->avatar_url,
            'email_verified' => (bool) $user->email_verified_at,
        ];

        // Include seller profile data if the user is a seller
        if ($user->isSeller() && $user->sellerProfile) {
            $data['seller_profile'] = [
                'store_name'        => $user->sellerProfile->store_name,
                'store_slug'        => $user->sellerProfile->store_slug,
                'logo_url'          => $user->sellerProfile->logo_url,
                'available_balance' => $user->sellerProfile->available_balance,
            ];
        }

        return ApiResponse::success($data);
    }

    /**
     * Log out the current session by revoking the active token.
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    /**
     * Send a password reset email.
     *
     * Always returns a success response regardless of whether the
     * email exists to prevent user enumeration attacks.
     *
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if ($user) {
            // Delete any existing reset token for this email
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            $token = Str::random(64);

            DB::table('password_reset_tokens')->insert([
                'email'      => $request->email,
                'token'      => Hash::make($token),
                'created_at' => now(),
            ]);

            $resetUrl = config('app.frontend_url')
                . '/reset-password?token='
                . $token
                . '&email='
                . urlencode($request->email);

            Mail::to($user->email)->send(new PasswordResetMail($resetUrl, $user->name));
        }

        return ApiResponse::success(
            null,
            'If that email address is registered, you will receive a password reset link shortly.'
        );
    }

    /**
     * Reset the password using a valid reset token.
     *
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return ApiResponse::error('This password reset link is invalid or has expired.', 422);
        }

        // Reset tokens expire after 60 minutes
        $createdAt = \Carbon\Carbon::parse($record->created_at);
        if ($createdAt->diffInMinutes(now()) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return ApiResponse::error('This password reset link has expired. Please request a new one.', 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return ApiResponse::error('No account found with this email address.', 404);
        }

        $user->update(['password' => $request->password]);

        // Delete the used token and revoke all active sessions
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        $user->tokens()->delete();

        return ApiResponse::success(null, 'Password reset successfully. You can now log in with your new password.');
    }
}