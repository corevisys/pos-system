<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DbSmsTemplate extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'db_smstemplates';

    protected $fillable = [
        'store_id', 'template_name', 'category', 'content',
        'variables_used', 'default_footer', 'language',
        'message_type', 'status', 'undelete_bit'
    ];

    protected $casts = [
        'variables_used' => 'array',
    ];
}
