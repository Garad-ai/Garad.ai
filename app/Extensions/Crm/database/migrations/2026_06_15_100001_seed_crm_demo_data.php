<?php

use App\Extensions\Crm\System\Models\CrmAiConversation;
use App\Extensions\Crm\System\Models\CrmCompany;
use App\Extensions\Crm\System\Models\CrmContact;
use App\Extensions\Crm\System\Models\CrmDeal;
use App\Extensions\Crm\System\Models\CrmDealStage;
use App\Extensions\Crm\System\Models\CrmDealStageChange;
use App\Extensions\Crm\System\Models\CrmNote;
use App\Extensions\Crm\System\Models\CrmPresentation;
use App\Extensions\Crm\System\Models\CrmPresentationSlide;
use App\Extensions\Crm\System\Models\CrmProject;
use App\Extensions\Crm\System\Models\CrmTask;
use App\Extensions\Crm\System\Models\SalesEstimate;
use App\Extensions\Crm\System\Models\SalesEstimateItem;
use App\Extensions\Crm\System\Models\SalesInvoice;
use App\Extensions\Crm\System\Models\SalesInvoiceItem;
use App\Extensions\Crm\System\Models\SalesPayment;
use App\Extensions\Crm\System\Models\SalesProposal;
use App\Extensions\Crm\System\Models\SalesProposalItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;

/**
 * Seeds a complete set of realistic demo data for the CRM extension so every
 * dashboard widget, report chart, list and detail page renders with content.
 *
 * The data is created for the first registered user and is idempotent — it
 * skips seeding if that user already has CRM companies.
 */
return new class extends Migration
{
    private int $userId;

    /** @var array<int, CrmDealStage> keyed by stage order */
    private array $stages = [];

    /** @var array<int, CrmCompany> */
    private array $companies = [];

    /** @var array<int, CrmContact> */
    private array $contacts = [];

    /** @var array<int, CrmDeal> */
    private array $deals = [];

    /** @var array<int, CrmProject> */
    private array $projects = [];

    private int $invoiceSeq = 0;

    public function up(): void
    {
        return;

        $user = User::query()->orderBy('id')->first();

        if (! $user) {
            return;
        }

        $this->userId = $user->id;

        if (CrmCompany::where('user_id', $this->userId)->exists()) {
            return;
        }

        $this->seedStages();
        $this->seedCompanies();
        $this->seedContacts();
        $this->seedDeals();
        $this->seedProjects();
        $this->seedTasks();
        $this->seedNotes();
        $this->seedSalesInvoices();
        $this->seedSalesEstimates();
        $this->seedSalesProposals();
        $this->seedPresentations();
        $this->seedAiConversations();
    }

    public function down(): void
    {
        return;

        $user = User::query()->orderBy('id')->first();

        if (! $user) {
            return;
        }

        $userId = $user->id;

        CrmPresentation::where('user_id', $userId)->each(fn (CrmPresentation $p) => $p->delete());
        CrmAiConversation::where('user_id', $userId)->delete();
        SalesPayment::where('user_id', $userId)->delete();
        SalesInvoice::where('user_id', $userId)->each(fn (SalesInvoice $i) => $i->delete());
        SalesEstimate::where('user_id', $userId)->each(fn (SalesEstimate $e) => $e->delete());
        SalesProposal::where('user_id', $userId)->each(fn (SalesProposal $p) => $p->delete());
        CrmNote::where('user_id', $userId)->delete();
        CrmTask::where('user_id', $userId)->delete();
        CrmProject::where('user_id', $userId)->delete();
        CrmDealStageChange::where('user_id', $userId)->delete();
        CrmDeal::where('user_id', $userId)->delete();
        CrmContact::where('user_id', $userId)->delete();
        CrmCompany::where('user_id', $userId)->delete();
        CrmDealStage::where('user_id', $userId)->delete();
    }

    private function seedStages(): void
    {
        CrmDealStage::createDefaultsForUser($this->userId);

        $this->stages = CrmDealStage::where('user_id', $this->userId)
            ->get()
            ->keyBy('order')
            ->all();
    }

    private function seedCompanies(): void
    {
        $rows = [
            ['name' => 'Acme Corporation', 'industry' => 'Manufacturing', 'website' => 'https://acme.example.com', 'phone' => '+1 415 555 0101', 'email' => 'hello@acme.example.com', 'address' => '500 Industrial Way', 'city' => 'San Francisco', 'state' => 'CA', 'country' => 'United States', 'is_favorite' => true],
            ['name' => 'Globex Inc.', 'industry' => 'Technology', 'website' => 'https://globex.example.com', 'phone' => '+1 212 555 0142', 'email' => 'contact@globex.example.com', 'address' => '1 Park Avenue', 'city' => 'New York', 'state' => 'NY', 'country' => 'United States', 'is_favorite' => true],
            ['name' => 'Initech LLC', 'industry' => 'Software', 'website' => 'https://initech.example.com', 'phone' => '+1 512 555 0188', 'email' => 'info@initech.example.com', 'address' => '700 Congress Ave', 'city' => 'Austin', 'state' => 'TX', 'country' => 'United States', 'is_favorite' => false],
            ['name' => 'Umbrella Health', 'industry' => 'Healthcare', 'website' => 'https://umbrella.example.com', 'phone' => '+44 20 7946 0991', 'email' => 'care@umbrella.example.com', 'address' => '12 King Street', 'city' => 'London', 'state' => '', 'country' => 'United Kingdom', 'is_favorite' => false],
            ['name' => 'Stark Industries', 'industry' => 'Aerospace', 'website' => 'https://stark.example.com', 'phone' => '+1 310 555 0177', 'email' => 'sales@stark.example.com', 'address' => '10880 Malibu Point', 'city' => 'Malibu', 'state' => 'CA', 'country' => 'United States', 'is_favorite' => true],
            ['name' => 'Wayne Enterprises', 'industry' => 'Finance', 'website' => 'https://wayne.example.com', 'phone' => '+1 312 555 0123', 'email' => 'info@wayne.example.com', 'address' => '1007 Mountain Drive', 'city' => 'Gotham', 'state' => 'NJ', 'country' => 'United States', 'is_favorite' => false],
            ['name' => 'Soylent Foods', 'industry' => 'Food & Beverage', 'website' => 'https://soylent.example.com', 'phone' => '+1 206 555 0150', 'email' => 'hello@soylent.example.com', 'address' => '88 Pike Street', 'city' => 'Seattle', 'state' => 'WA', 'country' => 'United States', 'is_favorite' => false],
            ['name' => 'Nordic Design AB', 'industry' => 'Retail', 'website' => 'https://nordic.example.com', 'phone' => '+46 8 555 0190', 'email' => 'hej@nordic.example.com', 'address' => 'Kungsgatan 24', 'city' => 'Stockholm', 'state' => '', 'country' => 'Sweden', 'is_favorite' => false],
        ];

        foreach ($rows as $i => $row) {
            $this->companies[] = $this->stamp(
                new CrmCompany(array_merge($row, [
                    'user_id' => $this->userId,
                    'notes'   => fake()->sentence(10),
                ])),
                now()->subDays(170 - $i * 10),
            );
        }
    }

    private function seedContacts(): void
    {
        $statuses = ['lead', 'active', 'customer', 'inactive'];
        $titles = ['CEO', 'CTO', 'Head of Sales', 'Procurement Manager', 'Marketing Director', 'Operations Lead', 'Product Manager', 'Finance Director', 'Account Executive', 'Founder'];

        // Spread created_at to populate "contact growth" (6 months), week-over-week and "new today".
        $daysAgo = [0, 0, 1, 2, 3, 9, 10, 11, 18, 25, 33, 47, 61, 78, 95, 112, 128, 145, 160, 172];

        for ($i = 0; $i < 20; $i++) {
            $company = $this->companies[$i % count($this->companies)];

            $this->contacts[] = $this->stamp(
                new CrmContact([
                    'user_id'        => $this->userId,
                    'crm_company_id' => $i % 6 === 5 ? null : $company->id,
                    'is_favorite'    => $i % 7 === 0,
                    'first_name'     => fake()->firstName(),
                    'last_name'      => fake()->lastName(),
                    'email'          => fake()->unique()->safeEmail(),
                    'phone'          => fake()->phoneNumber(),
                    'job_title'      => $titles[$i % count($titles)],
                    'status'         => $statuses[$i % count($statuses)],
                    'notes'          => fake()->sentence(12),
                ]),
                now()->subDays($daysAgo[$i])->setTime(9 + $i % 8, 15),
            );
        }
    }

    private function seedDeals(): void
    {
        $contact = fn (int $i): CrmContact => $this->contacts[$i % count($this->contacts)];
        $company = fn (int $i): CrmCompany => $this->companies[$i % count($this->companies)];

        // ── Active deals (Lead → Negotiation) — power the pipeline, forecast & velocity ──
        $active = [
            ['title' => 'Acme — Annual Platform License', 'value' => 48000, 'order' => 3, 'close' => now()->addDays(3), 'days' => 26, 'cur' => 'USD'],
            ['title' => 'Globex — Cloud Migration', 'value' => 72000, 'order' => 2, 'close' => now()->addDays(12), 'days' => 19, 'cur' => 'USD'],
            ['title' => 'Initech — TPS Reporting Suite', 'value' => 15500, 'order' => 1, 'close' => now()->addMonth()->day(10), 'days' => 8, 'cur' => 'USD'],
            ['title' => 'Umbrella — Patient Portal', 'value' => 33000, 'order' => 3, 'close' => now()->addMonths(2)->day(5), 'days' => 41, 'cur' => 'GBP'],
            ['title' => 'Stark — Defense Analytics', 'value' => 250000, 'order' => 2, 'close' => now()->addMonths(2)->day(20), 'days' => 33, 'cur' => 'USD'],
            ['title' => 'Wayne — Risk Dashboard', 'value' => 88000, 'order' => 1, 'close' => now()->addMonths(3)->day(8), 'days' => 12, 'cur' => 'USD'],
            ['title' => 'Soylent — Loyalty App', 'value' => 26500, 'order' => 0, 'close' => now()->addMonths(3)->day(22), 'days' => 2, 'cur' => 'USD'],
            ['title' => 'Nordic — E-commerce Redesign', 'value' => 41000, 'order' => 0, 'close' => now()->addDays(5), 'days' => 1, 'cur' => 'EUR'],
        ];

        foreach ($active as $i => $d) {
            $start = now()->subDays($d['days'])->setTime(11, 0);
            $path = range(0, $d['order']);

            $this->deals[] = $this->createDealWithHistory([
                'title'               => $d['title'],
                'value'               => $d['value'],
                'currency'            => $d['cur'],
                'crm_contact_id'      => $contact($i)->id,
                'crm_company_id'      => $company($i)->id,
                'expected_close_date' => $d['close'],
                'is_favorite'         => $i < 2,
                'description'         => fake()->paragraph(2),
            ], $path, $start, null);
        }

        // ── Won deals — one per month over the last 6 months (revenue chart) ──
        $won = [
            ['title' => 'Acme — Onboarding Package', 'value' => 18000, 'cur' => 'USD'],
            ['title' => 'Globex — Support Retainer', 'value' => 24000, 'cur' => 'USD'],
            ['title' => 'Initech — Custom Integration', 'value' => 31000, 'cur' => 'USD'],
            ['title' => 'Stark — Pilot Program', 'value' => 56000, 'cur' => 'USD'],
            ['title' => 'Wayne — Compliance Audit', 'value' => 42000, 'cur' => 'USD'],
            ['title' => 'Nordic — Brand Workshop', 'value' => 12500, 'cur' => 'EUR'],
        ];

        foreach ($won as $i => $d) {
            $closeAt = now()->subMonths($i)->day(15)->setTime(16, 0);
            $start = $closeAt->copy()->subDays(35);

            $this->deals[] = $this->createDealWithHistory([
                'title'               => $d['title'],
                'value'               => $d['value'],
                'currency'            => $d['cur'],
                'crm_contact_id'      => $contact($i + 2)->id,
                'crm_company_id'      => $company($i + 1)->id,
                'expected_close_date' => $closeAt->copy()->toDateString(),
                'is_favorite'         => false,
                'description'         => fake()->paragraph(2),
            ], [0, 1, 2, 3, 4], $start, $closeAt);
        }

        // ── Lost deals (win/loss ratio) ──
        $lost = [
            ['title' => 'Soylent — Vending Rollout', 'value' => 22000],
            ['title' => 'Umbrella — Legacy Replacement', 'value' => 67000],
            ['title' => 'Initech — Mobile Rebuild', 'value' => 19000],
        ];

        foreach ($lost as $i => $d) {
            $closeAt = now()->subMonths($i + 1)->day(20)->setTime(14, 0);
            $start = $closeAt->copy()->subDays(28);

            $this->deals[] = $this->createDealWithHistory([
                'title'               => $d['title'],
                'value'               => $d['value'],
                'currency'            => 'USD',
                'crm_contact_id'      => $contact($i + 5)->id,
                'crm_company_id'      => $company($i + 3)->id,
                'expected_close_date' => $closeAt->copy()->toDateString(),
                'is_favorite'         => false,
                'description'         => fake()->paragraph(2),
            ], [0, 1, 2, 5], $start, $closeAt);
        }
    }

    /**
     * Create a deal plus a realistic chain of stage changes.
     *
     * @param  array<string, mixed>  $attrs
     * @param  array<int, int>  $pathOrders  stage orders the deal moved through
     */
    private function createDealWithHistory(array $attrs, array $pathOrders, Carbon $startAt, ?Carbon $endAt): CrmDeal
    {
        $finalOrder = end($pathOrders);
        $changeAts = $this->distributeTimestamps($pathOrders, $startAt, $endAt);
        $lastAt = end($changeAts);

        $deal = $this->stamp(
            new CrmDeal(array_merge($attrs, [
                'user_id'           => $this->userId,
                'crm_deal_stage_id' => $this->stages[$finalOrder]->id,
            ])),
            $startAt,
            $lastAt,
        );

        $prevStageId = null;
        foreach ($pathOrders as $idx => $order) {
            $stage = $this->stages[$order];
            $this->stamp(
                new CrmDealStageChange([
                    'user_id'       => $this->userId,
                    'crm_deal_id'   => $deal->id,
                    'from_stage_id' => $prevStageId,
                    'to_stage_id'   => $stage->id,
                    'changed_at'    => $changeAts[$idx],
                ]),
                $changeAts[$idx],
            );
            $prevStageId = $stage->id;
        }

        return $deal;
    }

    /**
     * @param  array<int, int>  $pathOrders
     *
     * @return array<int, Carbon>
     */
    private function distributeTimestamps(array $pathOrders, Carbon $startAt, ?Carbon $endAt): array
    {
        $n = count($pathOrders);
        $stamps = [];

        if ($endAt) {
            $totalSeconds = $startAt->diffInSeconds($endAt);
            for ($i = 0; $i < $n; $i++) {
                $stamps[] = $startAt->copy()->addSeconds((int) ($totalSeconds * ($i / max(1, $n - 1))));
            }

            return $stamps;
        }

        $cursor = $startAt->copy();
        for ($i = 0; $i < $n; $i++) {
            $stamps[] = $cursor->copy();
            $cursor->addDays(5 + ($i * 3));
        }

        return $stamps;
    }

    private function seedProjects(): void
    {
        $rows = [
            ['name' => 'Acme Platform Implementation', 'status' => 'in_progress', 'priority' => 'high', 'category' => 'Implementation', 'budget' => 48000, 'start' => -20, 'due' => 25, 'deal' => 0, 'fav' => true],
            ['name' => 'Globex Cloud Migration', 'status' => 'in_progress', 'priority' => 'urgent', 'category' => 'Migration', 'budget' => 72000, 'start' => -12, 'due' => 40, 'deal' => 1, 'fav' => true],
            ['name' => 'Umbrella Portal Build', 'status' => 'not_started', 'priority' => 'medium', 'category' => 'Development', 'budget' => 33000, 'start' => 5, 'due' => 75, 'deal' => 3, 'fav' => false],
            ['name' => 'Stark Analytics Pilot', 'status' => 'on_hold', 'priority' => 'high', 'category' => 'Research', 'budget' => 56000, 'start' => -40, 'due' => 10, 'deal' => null, 'fav' => false],
            ['name' => 'Nordic Brand Workshop', 'status' => 'completed', 'priority' => 'low', 'category' => 'Design', 'budget' => 12500, 'start' => -60, 'due' => -8, 'deal' => null, 'fav' => false],
            ['name' => 'Wayne Compliance Audit', 'status' => 'completed', 'priority' => 'medium', 'category' => 'Consulting', 'budget' => 42000, 'start' => -90, 'due' => -20, 'deal' => null, 'fav' => false],
        ];

        foreach ($rows as $i => $r) {
            $contact = $this->contacts[$i % count($this->contacts)];
            $company = $this->companies[$i % count($this->companies)];

            $this->projects[] = $this->stamp(
                new CrmProject([
                    'user_id'        => $this->userId,
                    'is_favorite'    => $r['fav'],
                    'crm_contact_id' => $contact->id,
                    'crm_company_id' => $company->id,
                    'crm_deal_id'    => $r['deal'] !== null ? ($this->deals[$r['deal']]->id ?? null) : null,
                    'name'           => $r['name'],
                    'description'    => fake()->paragraph(3),
                    'status'         => $r['status'],
                    'priority'       => $r['priority'],
                    'category'       => $r['category'],
                    'start_date'     => now()->addDays($r['start'])->toDateString(),
                    'due_date'       => now()->addDays($r['due'])->toDateString(),
                    'completed_at'   => $r['status'] === 'completed' ? now()->addDays($r['due']) : null,
                    'budget'         => $r['budget'],
                    'currency'       => 'USD',
                    'notes'          => fake()->sentence(10),
                ]),
                now()->subDays(60 - $i * 5),
            );
        }
    }

    private function seedTasks(): void
    {
        $types = ['task', 'call', 'meeting', 'email', 'follow_up'];

        // ── Today's agenda (pending, due today, varied types) ──
        foreach (['meeting', 'call', 'email', 'follow_up', 'task'] as $i => $type) {
            $this->makeTask([
                'title'    => ucfirst(str_replace('_', ' ', $type)) . ' with ' . $this->contacts[$i]->first_name,
                'type'     => $type,
                'status'   => 'pending',
                'priority' => $i < 2 ? 'high' : 'medium',
                'due_date' => now()->setTime(10 + $i, 0),
                'contact'  => $i,
                'deal'     => $i,
            ], now()->subDays(2));
        }

        // ── Overdue pending tasks ──
        for ($i = 0; $i < 4; $i++) {
            $this->makeTask([
                'title'    => 'Overdue: ' . fake()->sentence(3),
                'type'     => $types[$i % count($types)],
                'status'   => 'pending',
                'priority' => $i % 2 === 0 ? 'high' : 'medium',
                'due_date' => now()->subDays(2 + $i)->setTime(15, 0),
                'contact'  => $i + 1,
                'deal'     => $i + 1,
            ], now()->subDays(10 + $i));
        }

        // ── Upcoming pending tasks (this week / next) ──
        for ($i = 0; $i < 6; $i++) {
            $this->makeTask([
                'title'    => 'Follow up: ' . fake()->sentence(3),
                'type'     => $types[$i % count($types)],
                'status'   => 'pending',
                'priority' => ['low', 'medium', 'high'][$i % 3],
                'due_date' => now()->addDays(1 + $i)->setTime(9 + $i, 30),
                'contact'  => $i + 2,
                'deal'     => $i + 2,
                'project'  => $i % 3,
            ], now()->subDays($i));
        }

        // ── Completed tasks spread over the last 8 weeks (task activity chart) ──
        for ($i = 0; $i < 16; $i++) {
            $completedAt = now()->subWeeks($i % 8)->subDays($i % 5)->setTime(13, 0);
            $this->makeTask([
                'title'    => 'Completed: ' . fake()->sentence(3),
                'type'     => $types[$i % count($types)],
                'status'   => 'completed',
                'priority' => ['low', 'medium', 'high'][$i % 3],
                'due_date' => $completedAt->copy()->subDay(),
                'contact'  => $i % count($this->contacts),
                'deal'     => $i % count($this->deals),
                'project'  => $i % count($this->projects),
            ], $completedAt->copy()->subDays(3), $completedAt);
        }

        // ── A couple of cancelled tasks (board completeness) ──
        for ($i = 0; $i < 2; $i++) {
            $this->makeTask([
                'title'    => 'Cancelled: ' . fake()->sentence(3),
                'type'     => 'task',
                'status'   => 'cancelled',
                'priority' => 'low',
                'due_date' => now()->subDays(5 + $i),
                'contact'  => $i,
            ], now()->subDays(12 + $i));
        }
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private function makeTask(array $r, Carbon $createdAt, ?Carbon $completedAt = null): void
    {
        $this->stamp(
            new CrmTask([
                'user_id'        => $this->userId,
                'crm_contact_id' => isset($r['contact']) ? $this->contacts[$r['contact']]->id : null,
                'crm_deal_id'    => isset($r['deal']) ? $this->deals[$r['deal']]->id : null,
                'crm_project_id' => isset($r['project']) ? $this->projects[$r['project']]->id : null,
                'title'          => $r['title'],
                'description'    => fake()->sentence(10),
                'type'           => $r['type'],
                'status'         => $r['status'],
                'priority'       => $r['priority'],
                'due_date'       => $r['due_date'] ?? null,
                'completed_at'   => $r['status'] === 'completed' ? ($completedAt ?? now()) : null,
            ]),
            $createdAt,
        );
    }

    private function seedNotes(): void
    {
        $types = ['note', 'call', 'meeting', 'email'];

        foreach (array_slice($this->contacts, 0, 8) as $i => $contact) {
            $this->stamp(
                new CrmNote([
                    'user_id'      => $this->userId,
                    'notable_type' => CrmContact::class,
                    'notable_id'   => $contact->id,
                    'type'         => $types[$i % count($types)],
                    'content'      => fake()->paragraph(2),
                ]),
                now()->subDays($i * 2 + 1),
            );
        }

        foreach (array_slice($this->deals, 0, 8) as $i => $deal) {
            $this->stamp(
                new CrmNote([
                    'user_id'      => $this->userId,
                    'notable_type' => CrmDeal::class,
                    'notable_id'   => $deal->id,
                    'type'         => $types[$i % count($types)],
                    'content'      => fake()->paragraph(2),
                ]),
                now()->subDays($i * 2 + 2),
            );
        }
    }

    private function seedSalesInvoices(): void
    {
        // status, tax %, discount, payment behaviour, days ago created
        $specs = [
            ['status' => 'paid', 'tax' => 8, 'discount' => 0, 'pay' => 'full', 'days' => 70],
            ['status' => 'paid', 'tax' => 10, 'discount' => 5, 'pay' => 'full', 'days' => 55],
            ['status' => 'partial', 'tax' => 8, 'discount' => 0, 'pay' => 'half', 'days' => 40],
            ['status' => 'sent', 'tax' => 5, 'discount' => 0, 'pay' => 'none', 'days' => 20],
            ['status' => 'overdue', 'tax' => 8, 'discount' => 0, 'pay' => 'none', 'days' => 45],
            ['status' => 'draft', 'tax' => 0, 'discount' => 0, 'pay' => 'none', 'days' => 6],
            ['status' => 'paid', 'tax' => 12, 'discount' => 10, 'pay' => 'full', 'days' => 30],
            ['status' => 'sent', 'tax' => 8, 'discount' => 0, 'pay' => 'none', 'days' => 9],
        ];

        $catalogue = [
            ['Consulting services', 8, 1200],
            ['Implementation hours', 40, 150],
            ['Annual license', 1, 9600],
            ['Support retainer', 3, 800],
            ['Custom development', 25, 180],
            ['Training workshop', 2, 1500],
        ];

        foreach ($specs as $i => $spec) {
            $contact = $this->contacts[$i % count($this->contacts)];
            $company = $this->companies[$i % count($this->companies)];
            $createdAt = now()->subDays($spec['days']);
            $this->invoiceSeq++;

            $invoice = $this->stamp(
                new SalesInvoice([
                    'user_id'        => $this->userId,
                    'crm_contact_id' => $contact->id,
                    'crm_company_id' => $company->id,
                    'crm_deal_id'    => $this->deals[$i % count($this->deals)]->id,
                    'invoice_number' => 'INV-' . str_pad((string) $this->invoiceSeq, 5, '0', STR_PAD_LEFT),
                    'status'         => $spec['status'],
                    'issue_date'     => $createdAt->toDateString(),
                    'due_date'       => $createdAt->copy()->addDays(30)->toDateString(),
                    'tax_rate'       => $spec['tax'],
                    'discount_type'  => $spec['discount'] > 0 ? 'percentage' : 'fixed',
                    'discount_value' => $spec['discount'],
                    'currency'       => 'USD',
                    'notes'          => fake()->sentence(8),
                    'from_name'      => 'MagicAI Demo Co.',
                    'from_email'     => 'billing@example.com',
                    'from_phone'     => '+1 800 555 0100',
                    'from_address'   => "200 Market Street\nSan Francisco, CA",
                ]),
                $createdAt,
            );

            $lineCount = 2 + ($i % 3);
            for ($l = 0; $l < $lineCount; $l++) {
                $item = $catalogue[($i + $l) % count($catalogue)];
                $this->stamp(
                    new SalesInvoiceItem([
                        'sales_invoice_id' => $invoice->id,
                        'description'      => $item[0],
                        'quantity'         => $item[1],
                        'unit'             => 'unit',
                        'unit_price'       => $item[2],
                        'total'            => $item[1] * $item[2],
                        'sort_order'       => $l,
                    ]),
                    $createdAt,
                );
            }

            $invoice->recalculateTotals();
            $invoice->refresh();

            if ($spec['pay'] !== 'none') {
                $amount = $spec['pay'] === 'full'
                    ? (float) $invoice->total
                    : round((float) $invoice->total / 2, 2);

                $this->stamp(
                    new SalesPayment([
                        'user_id'          => $this->userId,
                        'sales_invoice_id' => $invoice->id,
                        'crm_contact_id'   => $contact->id,
                        'crm_company_id'   => $company->id,
                        'amount'           => $amount,
                        'currency'         => 'USD',
                        'payment_date'     => $createdAt->copy()->addDays(7)->toDateString(),
                        'payment_method'   => ['bank_transfer', 'credit_card', 'paypal'][$i % 3],
                        'reference'        => 'PMT-' . strtoupper(fake()->bothify('####??')),
                        'status'           => 'completed',
                        'notes'            => null,
                    ]),
                    $createdAt->copy()->addDays(7),
                );

                $invoice->recalculateTotals();
                $invoice->update(['status' => $spec['status']]);
            }
        }

        // A standalone payment not tied to an invoice.
        $this->stamp(
            new SalesPayment([
                'user_id'        => $this->userId,
                'crm_contact_id' => $this->contacts[0]->id,
                'crm_company_id' => $this->companies[0]->id,
                'amount'         => 2500,
                'currency'       => 'USD',
                'payment_date'   => now()->subDays(3)->toDateString(),
                'payment_method' => 'cash',
                'reference'      => 'PMT-ADHOC1',
                'status'         => 'pending',
                'notes'          => 'Retainer deposit',
            ]),
            now()->subDays(3),
        );
    }

    private function seedSalesEstimates(): void
    {
        $statuses = ['draft', 'sent', 'accepted', 'rejected', 'expired'];
        $catalogue = [['Discovery phase', 1, 4000], ['UX design', 2, 2500], ['Frontend build', 30, 160], ['QA & testing', 12, 120]];

        for ($i = 0; $i < 5; $i++) {
            $contact = $this->contacts[$i % count($this->contacts)];
            $company = $this->companies[$i % count($this->companies)];
            $createdAt = now()->subDays(15 + $i * 9);

            $subtotal = 0;
            $items = [];
            $lineCount = 2 + ($i % 3);
            for ($l = 0; $l < $lineCount; $l++) {
                $c = $catalogue[($i + $l) % count($catalogue)];
                $lineTotal = $c[1] * $c[2];
                $subtotal += $lineTotal;
                $items[] = ['description' => $c[0], 'quantity' => $c[1], 'unit_price' => $c[2], 'total' => $lineTotal];
            }
            $taxRate = 8;
            $taxAmount = round($subtotal * $taxRate / 100, 2);

            $estimate = $this->stamp(
                new SalesEstimate([
                    'user_id'         => $this->userId,
                    'crm_contact_id'  => $contact->id,
                    'crm_company_id'  => $company->id,
                    'title'           => 'Estimate — ' . $company->name,
                    'estimate_number' => 'EST-' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                    'status'          => $statuses[$i],
                    'issue_date'      => $createdAt->toDateString(),
                    'valid_until'     => $createdAt->copy()->addDays(30)->toDateString(),
                    'subtotal'        => $subtotal,
                    'tax_rate'        => $taxRate,
                    'tax_amount'      => $taxAmount,
                    'total'           => $subtotal + $taxAmount,
                    'currency'        => 'USD',
                    'notes'           => fake()->sentence(8),
                ]),
                $createdAt,
            );

            foreach ($items as $item) {
                $this->stamp(new SalesEstimateItem(array_merge($item, ['sales_estimate_id' => $estimate->id])), $createdAt);
            }
        }
    }

    private function seedSalesProposals(): void
    {
        $statuses = ['draft', 'sent', 'accepted', 'rejected', 'expired'];
        $catalogue = [['Strategy & planning', 1, 6000], ['Platform setup', 1, 8500], ['Content migration', 20, 140], ['Go-live support', 5, 600]];

        for ($i = 0; $i < 5; $i++) {
            $contact = $this->contacts[$i % count($this->contacts)];
            $company = $this->companies[$i % count($this->companies)];
            $createdAt = now()->subDays(12 + $i * 11);

            $subtotal = 0;
            $items = [];
            $lineCount = 2 + ($i % 3);
            for ($l = 0; $l < $lineCount; $l++) {
                $c = $catalogue[($i + $l) % count($catalogue)];
                $lineTotal = $c[1] * $c[2];
                $subtotal += $lineTotal;
                $items[] = ['description' => $c[0], 'quantity' => $c[1], 'unit_price' => $c[2], 'total' => $lineTotal];
            }
            $taxRate = 10;
            $taxAmount = round($subtotal * $taxRate / 100, 2);

            $proposal = $this->stamp(
                new SalesProposal([
                    'user_id'         => $this->userId,
                    'crm_contact_id'  => $contact->id,
                    'crm_company_id'  => $company->id,
                    'crm_deal_id'     => $this->deals[$i % count($this->deals)]->id,
                    'title'           => 'Proposal — ' . $company->name,
                    'proposal_number' => 'PRP-' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                    'status'          => $statuses[$i],
                    'issue_date'      => $createdAt->toDateString(),
                    'valid_until'     => $createdAt->copy()->addDays(45)->toDateString(),
                    'subtotal'        => $subtotal,
                    'tax_rate'        => $taxRate,
                    'tax_amount'      => $taxAmount,
                    'total'           => $subtotal + $taxAmount,
                    'currency'        => 'USD',
                    'content'         => fake()->paragraphs(3, true),
                    'notes'           => fake()->sentence(8),
                ]),
                $createdAt,
            );

            foreach ($items as $item) {
                $this->stamp(new SalesProposalItem(array_merge($item, ['sales_proposal_id' => $proposal->id])), $createdAt);
            }
        }
    }

    private function seedPresentations(): void
    {
        $slideBlueprint = [
            ['type' => 'cover', 'title' => 'Partnership Proposal'],
            ['type' => 'company_overview', 'title' => 'About Us'],
            ['type' => 'problem', 'title' => 'The Challenge'],
            ['type' => 'solution', 'title' => 'Our Solution'],
            ['type' => 'pricing', 'title' => 'Investment'],
            ['type' => 'cta', 'title' => 'Let\'s Work Together'],
        ];

        for ($i = 0; $i < 3; $i++) {
            $deal = $this->deals[$i];
            $createdAt = now()->subDays(8 + $i * 6);

            $presentation = $this->stamp(
                new CrmPresentation([
                    'user_id'          => $this->userId,
                    'crm_deal_id'      => $deal->id,
                    'crm_company_id'   => $deal->crm_company_id,
                    'crm_contact_id'   => $deal->crm_contact_id,
                    'title'            => $deal->title . ' — Pitch Deck',
                    'status'           => 'completed',
                    'engine'           => 'fal',
                    'style'            => ['corporate', 'modern', 'minimal'][$i],
                    'total_slides'     => count($slideBlueprint),
                    'completed_slides' => count($slideBlueprint),
                    'completed_at'     => $createdAt->copy()->addMinutes(4),
                    'metadata'         => ['style' => ['corporate', 'modern', 'minimal'][$i]],
                ]),
                $createdAt,
            );

            foreach ($slideBlueprint as $s => $slide) {
                $this->stamp(
                    new CrmPresentationSlide([
                        'crm_presentation_id' => $presentation->id,
                        'sort_order'          => $s,
                        'slide_type'          => $slide['type'],
                        'title'               => $slide['title'],
                        'content'             => [
                            'subtitle'      => fake()->catchPhrase(),
                            'tagline'       => fake()->bs(),
                            'bullet_points' => [fake()->sentence(5), fake()->sentence(5), fake()->sentence(5)],
                            'description'   => fake()->paragraph(2),
                        ],
                        'image_prompt'        => 'Professional 16:9 ' . $slide['type'] . ' slide background, abstract gradient shapes.',
                        'status'              => 'completed',
                        'image_url'           => 'https://picsum.photos/seed/crm' . $i . $s . '/1280/720',
                    ]),
                    $createdAt,
                );
            }
        }
    }

    private function seedAiConversations(): void
    {
        $conversations = [
            [
                'title'    => 'Pipeline health check',
                'messages' => [
                    ['role' => 'user', 'content' => 'How is my sales pipeline looking this quarter?'],
                    ['role' => 'assistant', 'content' => 'You have several active deals across Lead through Negotiation, with a healthy forecast for the next three months. Your win rate is trending positively.'],
                ],
            ],
            [
                'title'    => 'Top deals to focus on',
                'messages' => [
                    ['role' => 'user', 'content' => 'Which deals should I prioritise this week?'],
                    ['role' => 'assistant', 'content' => 'Focus on the Stark Defense Analytics and Globex Cloud Migration deals — they carry the highest value and are close to their expected close dates.'],
                ],
            ],
            [
                'title'    => 'Overdue follow-ups',
                'messages' => [
                    ['role' => 'user', 'content' => 'Do I have any overdue tasks?'],
                    ['role' => 'assistant', 'content' => 'Yes, there are a few overdue follow-up tasks. I recommend clearing the high-priority ones tied to active deals first.'],
                ],
            ],
        ];

        foreach ($conversations as $i => $conversation) {
            $this->stamp(
                new CrmAiConversation([
                    'user_id'  => $this->userId,
                    'title'    => $conversation['title'],
                    'messages' => $conversation['messages'],
                ]),
                now()->subDays($i * 2 + 1),
            );
        }
    }

    /**
     * Persist a model with explicit timestamps, skipping model events/observers.
     */
    private function stamp(Model $model, Carbon $createdAt, ?Carbon $updatedAt = null): Model
    {
        $model->created_at = $createdAt;
        $model->updated_at = $updatedAt ?? $createdAt;
        $model->saveQuietly();

        return $model;
    }
};
