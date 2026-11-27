<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Support\MemberNumber;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_形をそろえる_違う形は_null(): void
    {
        $this->assertSame('T005', MemberNumber::normalize('T005'));
        $this->assertSame('T005', MemberNumber::normalize(' ｔ００５ '));
        $this->assertSame('T005', MemberNumber::normalize('T5'));
        $this->assertSame('T1000', MemberNumber::normalize('T1000'));
        foreach (['', 'T', 'T000', '005', 'S005', 'T-005', 'T5a'] as $bad) {
            $this->assertNull(MemberNumber::normalize($bad), $bad);
        }
    }

    public function test_番号を書かずに作ると_最大の次を振る_欠番は埋めない(): void
    {
        $this->assertSame('T001', Member::factory()->create()->member_no);
        Member::factory()->create(['member_no' => 'T042']);
        $this->assertSame('T043', Member::factory()->create()->member_no);

        // 退会した生徒さんの番号も使い回さない
        Member::factory()->create(['member_no' => 'T999', 'active' => false]);
        $this->assertSame('T1000', Member::factory()->create()->member_no);
        $this->assertSame('T1001', Member::factory()->create()->member_no);
    }

    public function test_同じ番号は入れられない_メールは無くてもよい(): void
    {
        Member::factory()->create(['member_no' => 'T001', 'email' => null]);
        Member::factory()->create(['email' => null]);
        $this->assertSame(2, Member::query()->whereNull('email')->count());

        $this->expectException(QueryException::class);
        Member::factory()->create(['member_no' => 'T001']);
    }
}
