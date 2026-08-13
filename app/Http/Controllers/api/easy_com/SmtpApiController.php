<?php

namespace App\Http\Controllers\api\easy_com;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPMailer\PHPMailer;

/**
 * SMTP Email settings  (table: smtp_details)
 *
 * Mirrors settings\smtpController with the fixes needed for a stateless client:
 *
 *  - tenant scoping on update()/destroy() (the Blade versions ran
 *    `where(id = ?)` with no sub_institute_id filter);
 *  - validation of email / host / port;
 *  - password is OPTIONAL on update. The Blade edit form re-posted the stored
 *    password in a visible field; a JSON client that omits it (or sends the
 *    masked placeholder) must not wipe the credential, so a blank password on
 *    update keeps the existing one;
 *  - the stored password is never echoed back - responses expose
 *    `has_password` instead;
 *  - one configuration per institute, because sendEmail() uses row [0].
 */
class SmtpApiController extends BaseEasyComApiController
{
    private const TABLE = 'smtp_details';

    /** GET /api/easy_com/smtp */
    public function index(Request $request): JsonResponse
    {
        return $this->run(function () {
            $rows = DB::table(self::TABLE)
                ->where('sub_institute_id', $this->subInstituteId())
                ->orderBy('id', 'desc')
                ->get()
                ->map(fn ($row) => $this->present($row));

            return $this->success($rows, 'Success');
        });
    }

    /** GET /api/easy_com/smtp/{id} — powers the Edit screen. */
    public function show(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($id) {
            $row = $this->findOwned($id);

            if (! $row) {
                return $this->error('SMTP configuration not found.', 404);
            }

            return $this->success($this->present($row), 'Success');
        });
    }

    /** POST /api/easy_com/smtp */
    public function store(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->validate($request, [
                'email'          => 'required|email|max:191',
                'password'       => 'required|string|max:191',
                'server_address' => 'required|string|max:191',
                'port'           => 'required|integer|min:1|max:65535',
            ]);

            if (DB::table(self::TABLE)->where('sub_institute_id', $this->subInstituteId())->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => ['An SMTP configuration already exists for this institute. Edit or delete it first.'],
                ]);
            }

            $id = DB::table(self::TABLE)->insertGetId([
                'gmail'            => $data['email'],
                'password'         => $data['password'],
                'server_address'   => $data['server_address'],
                'port'             => (string) $data['port'],
                'sub_institute_id' => $this->subInstituteId(),
            ]);

            return $this->success($this->present($this->findOwned($id)), 'SMTP configuration saved.', 201);
        });
    }

    /** PUT/PATCH /api/easy_com/smtp/{id} */
    public function update(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($request, $id) {
            $row = $this->findOwned($id);

            if (! $row) {
                return $this->error('SMTP configuration not found.', 404);
            }

            $data = $this->validate($request, [
                'email'          => 'required|email|max:191',
                'password'       => 'nullable|string|max:191',
                'server_address' => 'required|string|max:191',
                'port'           => 'required|integer|min:1|max:65535',
            ]);

            $update = [
                'gmail'          => $data['email'],
                'server_address' => $data['server_address'],
                'port'           => (string) $data['port'],
            ];

            // Blank / masked password => keep the stored credential.
            $password = (string) ($data['password'] ?? '');
            if ($password !== '' && ! $this->isMaskedPassword($password)) {
                $update['password'] = $password;
            }

            $unchanged = $update['gmail'] === $row->gmail
                && $update['server_address'] === $row->server_address
                && (string) $update['port'] === (string) $row->port
                && ! array_key_exists('password', $update);

            if ($unchanged) {
                return $this->success($this->present($row), 'No changes to save.');
            }

            DB::table(self::TABLE)
                ->where('id', $row->id)
                ->where('sub_institute_id', $this->subInstituteId())
                ->update($update);

            return $this->success($this->present($this->findOwned($row->id)), 'SMTP configuration updated.');
        });
    }

    /** DELETE /api/easy_com/smtp/{id} */
    public function destroy(Request $request, $id): JsonResponse
    {
        return $this->run(function () use ($id) {
            $row = $this->findOwned($id);

            if (! $row) {
                return $this->error('SMTP configuration not found.', 404);
            }

            DB::table(self::TABLE)
                ->where('id', $row->id)
                ->where('sub_institute_id', $this->subInstituteId())
                ->delete();

            return $this->success(null, 'SMTP configuration deleted.');
        });
    }

    /**
     * POST /api/easy_com/smtp/test  { to_email }
     *
     * Same intent as smtpController@CheckEmail, but it reports the real
     * PHPMailer error instead of a generic string, and returns
     * success:false when delivery fails (CheckEmail returned status_code 1 -
     * "success" - even for "You did not setup mail client").
     */
    public function test(Request $request): JsonResponse
    {
        return $this->run(function () use ($request) {
            $data = $this->validate($request, [
                'to_email' => 'required|email',
            ]);

            $smtp = $this->findFirstOwned();

            if (! $smtp) {
                return $this->error('No SMTP configuration found for this institute. Add one first.', 422);
            }

            $mail = new PHPMailer\PHPMailer(true);

            try {
                $mail->IsSMTP();
                $mail->isHTML(true);
                $mail->SMTPDebug = 0;
                $mail->SMTPAuth = true;
                $mail->SMTPSecure = ((int) $smtp->port === 465) ? 'ssl' : 'tls';
                $mail->Host = $smtp->server_address;
                $mail->Port = (int) $smtp->port;
                $mail->Username = $smtp->gmail;
                $mail->Password = $smtp->password;
                $mail->SetFrom($smtp->gmail, $smtp->gmail);
                $mail->AddReplyTo($smtp->gmail, $smtp->gmail);
                $mail->AddAddress($data['to_email']);
                $mail->Subject = 'Check SMTP Email';
                $mail->Body = 'Test For SMTP Email is OK';
                $mail->AltBody = 'Test For SMTP Email is OK';

                if (! $mail->Send()) {
                    return $this->error('Unable to send the test email: '.$mail->ErrorInfo, 422);
                }
            } catch (\Throwable $e) {
                return $this->error('Unable to send the test email: '.$e->getMessage(), 422);
            }

            return $this->success(null, 'Test email sent to '.$data['to_email'].'.');
        });
    }

    /* ------------------------------------------------------------------ */

    private function findOwned($id)
    {
        return DB::table(self::TABLE)
            ->where('id', $id)
            ->where('sub_institute_id', $this->subInstituteId())
            ->first();
    }

    private function findFirstOwned()
    {
        return DB::table(self::TABLE)
            ->where('sub_institute_id', $this->subInstituteId())
            ->first();
    }

    /** Never return the stored password to the browser. */
    private function present($row): array
    {
        return [
            'id'             => $row->id,
            'email'          => $row->gmail,
            'gmail'          => $row->gmail,
            'server_address' => $row->server_address,
            'port'           => $row->port,
            'has_password'   => ! empty($row->password),
            'created_at'     => $row->created_at ?? null,
        ];
    }

    private function isMaskedPassword(string $value): bool
    {
        return trim($value) !== '' && preg_match('/^[•*]+$/u', trim($value)) === 1;
    }
}
