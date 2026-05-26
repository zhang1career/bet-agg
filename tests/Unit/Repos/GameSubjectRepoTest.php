<?php

declare(strict_types=1);

namespace Tests\Unit\Repos;

use App\Models\GameGroup;
use App\Models\GameSubject;
use App\Repos\mall\GameSubjectRepo;
use Tests\TestCase;

final class GameSubjectRepoTest extends TestCase
{
    private GameSubjectRepo $subjects;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subjects = app(GameSubjectRepo::class);
    }

    public function test_createForAdmin_syncs_groups(): void
    {
        $group = new GameGroup(['code' => 'subj-grp-'.uniqid('', true)]);
        $group->save();

        $subject = $this->subjects->createForAdmin('Alpha', 'icon.png', '<p>Intro</p>', [(int) $group->id]);

        $subject->refresh();
        $this->assertSame('Alpha', $subject->name);
        $this->assertSame('<p>Intro</p>', $subject->info);
        $this->assertSame([(int) $group->id], $subject->groups->pluck('id')->all());
    }

    public function test_existsInAnyOfGroups(): void
    {
        $groupA = new GameGroup(['code' => 'ga-'.uniqid('', true)]);
        $groupA->save();
        $groupB = new GameGroup(['code' => 'gb-'.uniqid('', true)]);
        $groupB->save();

        $subject = $this->subjects->createForAdmin('Beta', '', '', [(int) $groupA->id]);

        $this->assertTrue($this->subjects->existsInAnyOfGroups((int) $subject->id, [(int) $groupA->id]));
        $this->assertFalse($this->subjects->existsInAnyOfGroups((int) $subject->id, [(int) $groupB->id]));
    }

    public function test_detachGroupsAndDelete(): void
    {
        $group = new GameGroup(['code' => 'del-subj-'.uniqid('', true)]);
        $group->save();
        $subject = $this->subjects->createForAdmin('Gamma', '', '', [(int) $group->id]);

        $this->subjects->detachGroupsAndDelete($subject);

        $this->assertNull(GameSubject::query()->whereKey($subject->id)->first());
    }
}
