<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DbStore extends Model
{
    use HasFactory;

    protected $table = 'db_store';

    protected $guarded = [];

    /**
     * Phase 3.3: SMTP credentials are encrypted at rest. 'smtp_host'/'smtp_port'/
     * 'smtp_user' remain plaintext (non-secret), but the password is transparently
     * encrypted on write and decrypted on read, so SmtpSettingsController's
     * display + testSmtp() continue to work unchanged.
     */
    protected $casts = [
        'smtp_pass' => 'encrypted',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
