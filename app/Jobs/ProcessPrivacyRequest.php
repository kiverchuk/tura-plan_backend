<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class ProcessPrivacyRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $requestId) {}

    public function handle(): void
    {
        $request = DB::table('privacy_requests')->where('id', $this->requestId)->first();
        if (! $request || $request->status === 'completed') {
            return;
        }
        DB::table('privacy_requests')->where('id', $this->requestId)->update(['status' => 'processing', 'updated_at' => now()]);
        if ($request->kind === 'export') {
            $tables = ['employers', 'calendars', 'shift_types', 'shifts', 'personal_events', 'rotation_templates', 'pay_rules', 'pay_rule_versions', 'shares', 'subscriptions', 'entitlements', 'sync_mutations', 'devices', 'consents', 'support_tickets'];
            $profile = DB::table('users')->where('id', $request->user_id)->first(['public_id', 'name', 'email', 'locale', 'region', 'created_at']);
            $export = ['generated_at' => now()->toISOString(), 'schema_version' => 1, 'profile' => $profile];
            foreach ($tables as $table) {
                $export[$table] = DB::table($table)->where('user_id', $request->user_id)->get()->all();
            }
            $path = "privacy/{$request->user_id}/{$request->id}.json";
            Storage::disk('local')->put($path, json_encode($export, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $details = json_decode($request->details ?? '{}', true, 512, JSON_THROW_ON_ERROR);
            $details['export_path'] = $path;
            DB::table('privacy_requests')->where('id', $this->requestId)->update(['details' => json_encode($details, JSON_THROW_ON_ERROR), 'status' => 'completed', 'completed_at' => now(), 'updated_at' => now()]);

            return;
        }
        if ($request->kind === 'erase') {
            foreach (['employers', 'calendars', 'shift_types', 'shifts', 'personal_events', 'rotation_templates', 'pay_rules', 'pay_rule_versions', 'shares', 'subscriptions', 'entitlements', 'sync_mutations', 'devices', 'consents', 'support_tickets', 'idempotency_keys'] as $table) {
                DB::table($table)->where('user_id', $request->user_id)->delete();
            }
            DB::table('users')->where('id', $request->user_id)->update(['name' => 'Deleted user', 'email' => 'deleted+'.$request->id.'@invalid.example', 'password' => str()->random(64), 'remember_token' => null, 'email_verified_at' => null, 'updated_at' => now()]);
            DB::table('privacy_requests')->where('user_id', $request->user_id)->where('id', '!=', $request->id)->delete();
        }
        if ($request->kind === 'rectify') {
            $details = json_decode($request->details ?? '{}', true, 512, JSON_THROW_ON_ERROR);
            $changes = $details['changes'] ?? [];
            DB::table('users')->where('id', $request->user_id)->update([...array_intersect_key($changes, array_flip(['name', 'locale', 'region'])), 'updated_at' => now()]);
        }
        DB::table('privacy_requests')->where('id', $this->requestId)->update(['status' => 'completed', 'completed_at' => now(), 'updated_at' => now()]);
    }
}
