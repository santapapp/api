<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations\Pages;

use App\Filament\Resources\Organizations\OrganizationResource;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateOrganization extends CreateRecord
{
    protected static string $resource = OrganizationResource::class;

    /**
     * Override handleRecordCreation untuk:
     * 1. Wrap dalam DB transaction
     * 2. Buat organization
     * 3. Proses bulk user dari Repeater members_data
     */
    protected function handleRecordCreation(array $data): Model
    {
        // Ambil dan hapus members_data dari data (bukan kolom di tabel organizations)
        $membersData = $data['members_data'] ?? [];
        unset($data['members_data']);

        $organization = DB::transaction(function () use ($data, $membersData): Organization {
            /** @var Organization $org */
            $org = Organization::create($data);

            $userCount     = 0;
            $attachedCount = 0;

            foreach ($membersData as $memberData) {
                $email = trim($memberData['email']);
                $name  = trim($memberData['name']);
                $role  = $memberData['role'];

                // Cari user existing atau buat baru
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'name'     => $name,
                        'password' => Hash::make(
                            filled($memberData['password'] ?? null)
                                ? $memberData['password']
                                : Str::random(16)
                        ),
                        'status'   => 'active',
                    ]
                );

                if ($user->wasRecentlyCreated) {
                    $userCount++;
                }

                // Attach ke organization (updateOrCreate untuk handle jika user sudah ada di org ini)
                OrganizationMember::updateOrCreate(
                    [
                        'organization_id' => $org->id,
                        'user_id'         => $user->id,
                    ],
                    [
                        'role' => $role,
                    ]
                );

                $attachedCount++;
            }

            // Simpan info untuk notifikasi
            $org->_createdUserCount     = $userCount;
            $org->_attachedMemberCount  = $attachedCount;

            return $org;
        });

        return $organization;
    }

    /**
     * Notifikasi sukses yang informatif setelah create
     */
    protected function getCreatedNotification(): ?Notification
    {
        $userCount    = $this->record->_createdUserCount ?? 0;
        $memberCount  = $this->record->_attachedMemberCount ?? 0;

        $body = $memberCount > 0
            ? "{$memberCount} user berhasil di-assign ({$userCount} user baru dibuat)."
            : 'Mitra berhasil didaftarkan.';

        return Notification::make()
            ->success()
            ->title('Mitra berhasil dibuat!')
            ->body($body);
    }
}
