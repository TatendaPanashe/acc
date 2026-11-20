<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Users created in tests have a confirmed email, so tests can use the app straight away.
     * Tests of the sign-up / email-confirmation flow set this to false to get real behaviour.
     */
    protected bool $usersStartVerified = true;

    protected function setUp(): void
    {
        parent::setUp();

        User::creating(function (User $user) {
            if ($this->usersStartVerified && !$user->email_verified_at) {
                $user->email_verified_at = now();
            }
        });
    }
}
