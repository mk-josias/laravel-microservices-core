<?php

declare(strict_types=1);

namespace Microservices\Tests\Fixtures\App\Models;

use Illuminate\Database\Eloquent\Model;
use Microservices\Contracts\Shadows\Shadowed;
use Microservices\Traits\ShadowSource;

/**
 * @property int $id
 * @property int $amount
 */
final class Refund extends Model implements Shadowed
{
    use ShadowSource;

    public $timestamps = false;

    protected $guarded = [];

    /** @var list<string> */
    protected array $shadowed = ['amount'];

    public function shadowStream(): string
    {
        return 'shadows';
    }
}
