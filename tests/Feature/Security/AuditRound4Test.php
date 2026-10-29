<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\RequireHrRole;
use App\Support\TenantDeleteGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuditRound4Test extends TestCase
{
    private function hr(string $profile, bool $student = false): int
    {
        session()->put('user_profile_name', $profile);
        session()->put('is_student', $student);

        return (new RequireHrRole())->handle(Request::create('/x'), fn () => response('ok'))->getStatusCode();
    }

    public function test_hr_routes_refuse_teachers_librarians_and_students(): void
    {
        foreach (['Teacher', 'Librarian', 'Accountant', ''] as $p) {
            $this->assertSame(403, $this->hr($p), $p);
        }
        $this->assertSame(403, $this->hr('HR Manager', true));
        foreach (['Admin', 'School Admin', 'HR Manager', 'Principal', 'Human Resources'] as $p) {
            $this->assertSame(200, $this->hr($p), $p);
        }
    }

    public function test_hr_sensitive_routes_carry_the_middleware(): void
    {
        $need = ['job-applications', 'offers', 'offboarding/cases', 'mobility/promotions', 'mobility/transfers', 'talent/admin/workflows'];
        $found = [];
        foreach (Route::getRoutes() as $r) {
            foreach ($need as $n) {
                if (str_ends_with($r->uri(), $n) && in_array('GET', $r->methods(), true)) {
                    $found[$n] = in_array('hr.manage', $r->gatherMiddleware(), true);
                }
            }
        }
        $this->assertCount(count($need), $found);
        foreach ($found as $n => $ok) {
            $this->assertTrue($ok, $n);
        }
    }

    public function test_online_receipt_needs_staff(): void
    {
        foreach (Route::getRoutes() as $r) {
            if ($r->uri() === 'fees/get_online_receipt') {
                $mw = $r->gatherMiddleware();
                $this->assertContains('api.session', $mw);
                $this->assertContains('staff.only', $mw);
                $this->postJson('/fees/get_online_receipt', ['student_id' => 1])->assertStatus(401);

                return;
            }
        }
        $this->fail('route missing');
    }

    public function test_guard_only_trusts_a_table_read_by_the_url_id(): void
    {
        $this->assertTrue(TenantDeleteGuard::firstQueryUses('$x = DB::table("a")->where("id", $id)->first();', 'id'));
        $this->assertTrue(TenantDeleteGuard::firstQueryUses('$m = Thing::find($id);', 'id'));
        $this->assertFalse(TenantDeleteGuard::firstQueryUses('$s = DB::table("lookup")->where("id", $request->x)->first(); $y = A::find($id);', 'id'));
        $this->assertFalse(TenantDeleteGuard::firstQueryUses('return view("x");', 'id'));
    }
}
