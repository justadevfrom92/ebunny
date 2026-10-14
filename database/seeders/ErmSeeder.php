<?php

namespace Database\Seeders;

use App\Models\ErmCase;
use App\Models\ErmCheckin;
use App\Models\ErmChecklistItem;
use App\Models\ErmCourse;
use App\Models\ErmEnrollment;
use App\Models\ErmKudo;
use App\Models\ErmReview;
use App\Models\ErmTimeOff;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/** FICTIONAL Bunkhouse sample: reviews, check-ins, training, kudos, time off, HR cases and onboarding/offboarding for every sample user. */
class ErmSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(1357);
        $people = User::where('active', true)->orderBy('id')->get();
        $hr = User::where('email', 'hr@example.com')->value('id');
        $rory = User::where('email', 'like', 'rory.kindwell@%')->value('id') ?? $hr;
        $pick = fn ($list) => $list[mt_rand(0, count($list) - 1)];
        $at = fn (int $daysAgo) => now()->subDays($daysAgo)->setTime(mt_rand(8, 17), mt_rand(0, 59))->min(now()->subMinutes(mt_rand(5, 90)));

        // ---- New hire starting soon, one who just started, and a contract ending ----
        $newHire = User::where('email', 'like', 'casey.notreal@%')->first();
        $justStarted = User::where('email', 'like', 'emerson.botley@%')->first();
        $ending = User::where('email', 'like', 'indy.r@%')->first();
        $newHire?->update(['hired_on' => today()->addDays(6)]);
        $justStarted?->update(['hired_on' => today()->subDays(9)]);
        $ending?->update(['access_ends_on' => today()->addDays(18)]);

        // ---- Training courses ----
        $courses = collect([
            ['Customer Privacy & Data Handling', 'Compliance', ['all'], 1, 12, 'How we protect account data, SSNs and payment details, and what to do if something goes wrong.'],
            ['Texas Customer Protection Rules', 'Compliance', ['Customer Service', 'Marketing', 'external'], 1.5, 12, 'Disclosures, enrollment consent, door-to-door and phone sales rules for Texas electricity.'],
            ['Phishing & Password Safety', 'Compliance', ['all'], 0.5, 12, 'Spotting phishing, using the password manager and reporting suspicious messages.'],
            ['Corral for New Agents', 'Systems', ['Customer Service', 'external'], 3, null, 'Searching accounts, notes, orders, the exception queues and texting customers.'],
            ['De-escalation on the Phone', 'Customer Care', ['Customer Service'], 2, null, 'Calming upset callers, high-bill conversations and when to hand off to a lead.'],
            ['Reading an ERCOT Transaction', 'Product', [], 1, null, 'Enrollments, move-ins, switches and rejections, and what each code means.'],
            ['Managing People Here', 'Leadership', ['Management'], 4, null, 'One-on-ones, reviews, time off approvals and when to open an HR case.'],
            ['Office Safety & Emergency Plan', 'Safety', ['all'], 0.5, 24, 'Exits, severe weather, first aid kits and who to call.'],
        ])->map(fn ($c) => ErmCourse::create(['name' => $c[0], 'category' => $c[1], 'required_for' => $c[2], 'hours' => $c[3], 'renew_months' => $c[4], 'description' => $c[5]]));

        foreach ($people as $u) {
            foreach ($courses as $c) {
                $required = $c->requiredFor($u);
                if (! $required && mt_rand(0, 4)) {
                    continue;   // optional courses: about one in five people take them
                }
                $assigned = Carbon::parse($u->hired_on && $u->hired_on->isPast() && $u->hired_on->gt(today()->subMonths(10)) ? $u->hired_on : today()->subDays(mt_rand(20, 200)));
                $due = $assigned->copy()->addDays(30);
                $roll = mt_rand(1, 10);
                $done = $due->isPast() ? $roll <= 8 : $roll <= 3;
                ErmEnrollment::create(['course_id' => $c->id, 'user_id' => $u->id, 'assigned_on' => $assigned, 'due_on' => $due,
                    'completed_on' => $done ? $assigned->copy()->addDays(mt_rand(2, 28))->min(today()) : null, 'score' => $done ? mt_rand(78, 100) : null]);
            }
        }

        // ---- Reviews: last period done, this period open (some overdue) ----
        $strengths = ['Customers ask for them by name; calm on the hardest calls.', 'Cleared the ERCOT exception backlog two months running.', 'Clear, careful writing; the rate pages have never been better.',
            'Owns problems end to end and keeps everyone posted.', 'Great at teaching new hires the systems.', 'Reliable, on time, and quick to pick up new work.'];
        $growth = ['Hand off more of the routine work so you can lead projects.', 'Speak up earlier when a deadline is at risk.', 'Build a deeper understanding of billing and ERCOT.', 'Take more ownership in team meetings.'];
        $goals = ['Lead the new-agent training rotation.', 'Cut average handle time by 30 seconds without hurting satisfaction.', 'Finish the Managing People course.', 'Own the quarterly rate review.'];
        foreach ($people as $u) {
            if ($u->hired_on && $u->hired_on->gt(today()->subMonths(2))) {
                ErmReview::create(['user_id' => $u->id, 'reviewer_id' => $u->manager_id, 'kind' => '90-day', 'period' => 'First 90 days', 'due_on' => ($u->hired_on->isFuture() ? $u->hired_on : $u->hired_on)->copy()->addDays(90), 'status' => 'scheduled']);

                continue;
            }
            $kind = $u->company ? 'Contractor check-in' : 'Annual';
            $rating = $pick([3, 3, 3, 4, 4, 4, 5, 2]);
            ErmReview::create(['user_id' => $u->id, 'reviewer_id' => $u->manager_id, 'kind' => $kind, 'period' => (now()->year - 1).' H2', 'due_on' => Carbon::create(now()->year, 1, 31),
                'status' => 'complete', 'rating' => $rating, 'strengths' => $pick($strengths), 'growth' => $pick($growth), 'goals' => $pick($goals),
                'self_review' => 'I\'m proud of the work this year and want to grow into more ownership.', 'completed_at' => Carbon::create(now()->year, 1, mt_rand(10, 30), 15)]);
            $status = $pick(['scheduled', 'self', 'manager', 'complete', 'complete']);
            $due = today()->addDays(mt_rand(-20, 40));
            ErmReview::create(['user_id' => $u->id, 'reviewer_id' => $u->manager_id, 'kind' => $u->company ? 'Contractor check-in' : 'Mid-year', 'period' => now()->year.' H1', 'due_on' => $due, 'status' => $status,
                'rating' => $status === 'complete' ? $pick([3, 4, 4, 5]) : null, 'strengths' => in_array($status, ['manager', 'complete'], true) ? $pick($strengths) : null,
                'growth' => $status === 'complete' ? $pick($growth) : null, 'goals' => $status === 'complete' ? $pick($goals) : null,
                'self_review' => $status !== 'scheduled' ? 'Took on the summer peak and helped train two new people.' : null,
                'completed_at' => $status === 'complete' ? $due->copy()->min(today())->setTime(14, 0) : null]);
        }

        // ---- One-on-ones (everyone with a manager, a handful due for one) ----
        $topics = ['Summer call volume and staffing.', 'Career goals: interested in the lead role.', 'Feedback on the new Corral CRM pages.', 'Workload is heavy with the exception queues.', 'Training plan for next quarter.', 'Wants to cross-train on billing.'];
        $actions = ['Shadow a lead for two shifts.', 'Move two queues to the overflow team.', 'Sign up for the ERCOT course.', 'Revisit in two weeks.', null];
        foreach ($people->whereNotNull('manager_id') as $i => $u) {
            $last = $i % 6 === 0 ? mt_rand(35, 60) : mt_rand(1, 25);
            foreach ([$last, $last + mt_rand(25, 40), $last + mt_rand(55, 80)] as $days) {
                $mood = $pick([3, 4, 4, 5, 4, 3, 2]);
                ErmCheckin::create(['user_id' => $u->id, 'manager_id' => $u->manager_id, 'held_on' => today()->subDays($days), 'mood' => $mood, 'topics' => $pick($topics), 'action_items' => $pick($actions),
                    'created_at' => $at($days), 'updated_at' => $at($days)]);
            }
        }

        // ---- Kudos ----
        $notes = ['Stayed late to help a customer whose power was about to be disconnected.', 'Walked me through the ERCOT rejection codes. Huge help!', 'Covered my queue while I was out sick.',
            'Turned an angry caller into a five-star survey.', 'Found the billing bug before it went out to customers.', 'Made the new rate page so much clearer.', 'Always the first to jump in on a busy Monday.',
            'Handled the weekend overflow like a pro.', 'Great job onboarding the Brightline agents.', 'Kept the whole team laughing during the storm week.'];
        foreach (range(1, 80) as $k) {
            [$from, $to] = [$people->random(), $people->random()];
            if ($from->is($to)) {
                continue;
            }
            $when = $at(mt_rand(0, 120));
            ErmKudo::create(['from_id' => $from->id, 'to_id' => $to->id, 'value' => $pick(ErmKudo::VALUES), 'message' => $pick($notes), 'created_at' => $when, 'updated_at' => $when]);
        }
        // Everyone has received at least one
        foreach ($people as $u) {
            if (! ErmKudo::where('to_id', $u->id)->exists()) {
                $when = $at(mt_rand(1, 60));
                ErmKudo::create(['from_id' => $u->manager_id ?? $hr, 'to_id' => $u->id, 'value' => $pick(ErmKudo::VALUES), 'message' => $pick($notes), 'created_at' => $when, 'updated_at' => $when]);
            }
        }

        // ---- Time off: past, out now, upcoming, waiting and denied ----
        foreach ($people as $i => $u) {
            $plans = [[-mt_rand(40, 200), mt_rand(0, 4), 'approved']];
            if ($i % 5 === 0) {
                $plans[] = [-1, mt_rand(1, 4), 'approved'];        // out now
            } elseif ($i % 5 === 1) {
                $plans[] = [mt_rand(2, 12), mt_rand(0, 3), 'approved'];
            } elseif ($i % 7 === 2) {
                $plans[] = [mt_rand(10, 40), mt_rand(1, 6), 'requested'];
            } elseif ($i % 11 === 3) {
                $plans[] = [mt_rand(5, 30), 2, 'denied'];
            }
            foreach ($plans as [$start, $len, $status]) {
                $s = today()->addDays($start);
                while ($s->isWeekend()) {
                    $s->addDay();
                }
                $e = $s->copy()->addDays($len);
                $hours = 8 * collect($s->daysUntil($e))->filter(fn ($d) => $d->isWeekday())->count();
                ErmTimeOff::create(['user_id' => $u->id, 'type' => $len === 0 && mt_rand(0, 1) ? 'Sick' : $pick(['PTO', 'PTO', 'PTO', 'Volunteer', 'Jury Duty']), 'starts_on' => $s, 'ends_on' => $e, 'hours' => max(8, $hours),
                    'status' => $status, 'approver_id' => $status === 'requested' ? null : ($u->manager_id ?? $hr), 'decided_at' => $status === 'requested' ? null : $s->copy()->subDays(10)->min(now()),
                    'note' => $status === 'denied' ? 'Overlaps the month-end close.' : ($status === 'requested' ? $pick(['Family trip', 'Moving to a new apartment', 'Kid\'s graduation']) : null)]);
            }
        }

        // ---- HR cases ----
        $cases = [
            ['Concern', 'Schedule conflicts on the weekend rotation', 'High', 'open', 'Raised that weekend shifts aren\'t being shared evenly.'],
            ['Accommodation', 'Standing desk and headset request', 'Medium', 'in_progress', 'Asked for a standing desk for a back condition. Doctor\'s note received.'],
            ['Coaching', 'Call handle time above team average', 'Low', 'in_progress', 'Handle time 40% above average for 3 weeks. Coaching plan started.'],
            ['Policy Question', 'Can PTO roll over into next year?', 'Low', 'closed', 'Asked whether unused PTO rolls over.', 'Up to 40 hours roll over; shared the handbook section.'],
            ['Conflict', 'Disagreement between two agents about queue ownership', 'Medium', 'closed', 'Two agents both working the same exception queue.', 'Split the queues by market. Both agreed.'],
            ['Pay or Benefits', 'Question about overtime on holiday shifts', 'Medium', 'open', 'Asked how overtime is paid on holidays.'],
            ['Performance Plan', '60-day performance plan', 'High', 'in_progress', 'Missed quality targets three months in a row. Plan agreed with goals and weekly check-ins.'],
            ['Concern', 'Contract agent shared a screenshot with account details', 'High', 'closed', 'A Brightline agent posted a screenshot with an account number in a group chat.', 'Removed, agent retrained on privacy; Brightline confirmed with their team.'],
            ['Accommodation', 'Flexible start time for school drop-off', 'Low', 'closed', 'Asked to start at 9:30 instead of 8:00.', 'Approved: 9:30–6:00 schedule.'],
            ['Coaching', 'Tone in customer emails', 'Low', 'open', 'A few customer complaints about short email replies.'],
        ];
        foreach ($cases as $k => $c) {
            $u = $people[($k * 3 + 2) % $people->count()];
            if ($k === 7) {
                $u = User::where('company', 'Brightline Contact Solutions')->where('active', true)->first() ?? $u;
            }
            $opened = $at(mt_rand(5, 120));
            $case = ErmCase::create(['user_id' => $u->id, 'opened_by' => $u->manager_id ?? $hr, 'assigned_to' => $k % 2 ? $hr : $rory, 'type' => $c[0], 'subject' => $c[1], 'priority' => $c[2], 'status' => $c[3],
                'resolution' => $c[5] ?? null, 'closed_at' => $c[3] === 'closed' ? $opened->copy()->addDays(mt_rand(3, 20))->min(now()) : null]);
            $case->forceFill(['created_at' => $opened, 'updated_at' => $opened])->saveQuietly();
            $case->updates()->create(['user_id' => $case->opened_by, 'body' => $c[4], 'created_at' => $opened, 'updated_at' => $opened]);
            if ($c[3] !== 'open') {
                $mid = $opened->copy()->addDays(2)->min(now());
                $case->updates()->create(['user_id' => $case->assigned_to, 'body' => 'Met with '.explode(' ', $u->name)[0].' to talk it through. Next steps agreed.', 'created_at' => $mid, 'updated_at' => $mid]);
            }
            if ($c[3] === 'closed') {
                $case->updates()->create(['user_id' => $case->assigned_to, 'body' => 'Closed: '.$c[5], 'created_at' => $case->closed_at, 'updated_at' => $case->closed_at]);
            }
        }

        // ---- Former people keep their history from before they left ----
        foreach (User::where('active', false)->get() as $u) {
            $left = today()->subMonths(3);
            ErmReview::create(['user_id' => $u->id, 'reviewer_id' => $u->manager_id ?? $hr, 'kind' => $u->company ? 'Contractor check-in' : 'Annual', 'period' => (now()->year - 1).' H2',
                'due_on' => $left->copy()->subMonths(6), 'status' => 'complete', 'rating' => 3, 'strengths' => $pick($strengths), 'growth' => $pick($growth), 'completed_at' => $left->copy()->subMonths(6)]);
            ErmCheckin::create(['user_id' => $u->id, 'manager_id' => $u->manager_id ?? $hr, 'held_on' => $left->copy()->subDays(20), 'mood' => 3, 'topics' => 'Talked about the move and handing off open work.', 'action_items' => 'Write up queue notes before the last day.']);
            foreach ($courses->filter(fn ($c) => $c->requiredFor($u)) as $c) {
                ErmEnrollment::create(['course_id' => $c->id, 'user_id' => $u->id, 'assigned_on' => $left->copy()->subMonths(8), 'due_on' => $left->copy()->subMonths(7), 'completed_on' => $left->copy()->subMonths(7)->subDays(3), 'score' => mt_rand(80, 98)]);
            }
            ErmTimeOff::create(['user_id' => $u->id, 'type' => 'PTO', 'starts_on' => $left->copy()->subMonths(2)->startOfWeek(), 'ends_on' => $left->copy()->subMonths(2)->startOfWeek()->addDays(2), 'hours' => 24, 'status' => 'approved', 'approver_id' => $u->manager_id ?? $hr, 'decided_at' => $left->copy()->subMonths(2)->subDays(14)]);
            $when = $left->copy()->subDays(5)->setTime(10, 0);
            ErmKudo::create(['from_id' => $u->manager_id ?? $hr, 'to_id' => $u->id, 'value' => 'Teamwork', 'message' => 'Thanks for the great handoff notes on your way out. We\'ll miss you!', 'created_at' => $when, 'updated_at' => $when]);
        }

        // ---- Onboarding and offboarding ----
        foreach ([[$newHire, 'onboarding', today()->addDays(6)], [$justStarted, 'onboarding', today()->subDays(9)], [$ending, 'offboarding', today()->addDays(18)],
            [User::where('email', 'former@example.com')->first(), 'offboarding', today()->subMonths(3)], [User::where('email', 'kai.f@brightline.example.net')->first(), 'offboarding', today()->subMonths(2)->endOfMonth()]] as [$u, $kind, $day]) {
            if (! $u) {
                continue;
            }
            ErmChecklistItem::start($u, $kind, $day);
            // Steps due before today are done (a couple left behind to show overdue work)
            foreach (ErmChecklistItem::where('user_id', $u->id)->where('kind', $kind)->orderBy('position')->get() as $n => $item) {
                if ($item->due_on->lt(today()) && ! ($u->is($justStarted) && $n === 5)) {
                    $item->update(['done_at' => $item->due_on->copy()->setTime(11, 0), 'done_by' => $item->team === 'IT' ? User::where('email', 'admin@example.com')->value('id') : $hr]);
                }
            }
        }
    }
}
