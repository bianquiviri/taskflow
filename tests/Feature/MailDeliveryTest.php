<?php

declare(strict_types=1);

use App\Actions\AddCommentAction;
use App\Actions\InviteTeamMemberAction;
use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

/*
|--------------------------------------------------------------------------
| Transactional mail delivery
|--------------------------------------------------------------------------
|
| The other suites assert that a notification was *queued*
| (Notification::fake() / Queue::fake()). These tests let the whole pipeline
| run instead: phpunit.xml pins MAIL_MAILER=array and QUEUE_CONNECTION=sync, so
| a queued notification is processed inline and really goes through the mailer.
| Reading the transport's messages catches regressions in the recipient,
| subject or channel end to end.
|
*/

/**
 * The messages that reached the mailer during the current test.
 *
 * @return Collection<int, Email>
 */
function deliveredMails(): Collection
{
    return Mail::mailer()
        ->getSymfonyTransport()
        ->messages()
        ->map(fn ($sent) => $sent->getOriginalMessage());
}

it('delivers a password reset email through the mailer', function () {
    $user = User::factory()->create(['email' => 'ada@taskflow.test']);

    $this->post(route('password.email'), ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $mails = deliveredMails();

    expect($mails)->toHaveCount(1);

    $email = $mails->sole();

    expect($email)->toBeInstanceOf(Email::class)
        ->and($email->getSubject())->toBe('Reset your password')
        ->and($email->getTo())->toHaveCount(1)
        ->and($email->getTo()[0]->getAddress())->toBe('ada@taskflow.test');
});

it('delivers an email verification message through the mailer', function () {
    $user = User::factory()->unverified()->create(['email' => 'ada@taskflow.test']);

    $this->actingAs($user)
        ->post(route('verification.send'))
        ->assertRedirect()
        ->assertSessionHas('status', 'verification-link-sent');

    $mails = deliveredMails();

    expect($mails)->toHaveCount(1);

    $email = $mails->sole();

    expect($email)->toBeInstanceOf(Email::class)
        ->and($email->getSubject())->toBe('Verify your email address')
        ->and($email->getTo()[0]->getAddress())->toBe('ada@taskflow.test');
});

it('delivers a team invitation email through the mailer', function () {
    $team = Team::factory()->create();

    app(InviteTeamMemberAction::class)($team, 'invitee@taskflow.test');

    $mails = deliveredMails();

    expect($mails)->toHaveCount(1);

    $email = $mails->sole();

    expect($email)->toBeInstanceOf(Email::class)
        ->and($email->getSubject())->toBe(sprintf('You have been invited to join "%s"', $team->name))
        ->and($email->getTo()[0]->getAddress())->toBe('invitee@taskflow.test');
});

it('delivers a mention email through the mailer', function () {
    $author = User::factory()->create(['name' => 'Ada Lovelace']);
    $mentioned = User::factory()->create(['name' => 'Grace Hopper', 'email' => 'grace@taskflow.test']);
    $project = Project::factory()->for($author, 'owner')->create();
    $project->members()->create(['user_id' => $mentioned->id, 'role' => ProjectRole::Member]);
    $task = Task::factory()->for($project, 'project')->create(['title' => 'Ship the release']);

    (new AddCommentAction())($task, $author, 'Please review @grace-hopper');

    $mails = deliveredMails();

    expect($mails)->toHaveCount(1);

    $email = $mails->sole();

    expect($email)->toBeInstanceOf(Email::class)
        ->and($email->getSubject())->toBe('You were mentioned in "Ship the release"')
        ->and($email->getTo()[0]->getAddress())->toBe('grace@taskflow.test');
});
