<?php

declare(strict_types=1);

namespace App\Extensions\AIChatPro\System\Http\Controllers;

use App\Helpers\Classes\Helper;
use App\Http\Controllers\Controller;
use App\Jobs\SendTeamInviteEmail;
use App\Models\Team\Team;
use App\Models\Team\TeamMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AgentWorkspaceController extends Controller
{
    private const ASSIGNABLE_ROLES = ['admin', 'manager', 'member', 'approver', 'viewer'];

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $teams = Team::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhereHas('members', fn ($members) => $members
                        ->where('user_id', $user->id)
                        ->where('status', 'active'));
            })
            ->with(['user:id,name,surname,email', 'members.user:id,name,surname,email'])
            ->orderBy('name')
            ->get()
            ->map(fn (Team $team): array => $this->teamPayload($team, $user))
            ->all();

        return response()->json($teams)->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
        ]);
        abort_unless((bool) Helper::setting('team_functionality'), 403, 'Team workspaces are not enabled.');

        $plan = $user->relationPlan;
        abort_unless(
            $user->isAdmin() || ($plan && $plan->is_team_plan && (int) $plan->plan_allow_seat > 0),
            403,
            'An active team plan with available seats is required to create a workspace.'
        );

        $team = Team::query()->create([
            'user_id'     => $user->id,
            'name'        => trim($validated['name']),
            'allow_seats' => $user->isAdmin() ? 100 : (int) $plan->plan_allow_seat,
        ]);

        return response()->json($this->teamPayload($team, $user), 201);
    }

    public function invite(Request $request, Team $team): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->authorizeTeamManagement($actor, $team);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role'  => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
        ]);
        abort_if(
            $actor->id !== $team->user_id && $validated['role'] === 'admin',
            403,
            'Only the workspace owner can assign administrators.'
        );

        $member = DB::transaction(function () use ($team, $validated, $actor): TeamMember {
            $lockedTeam = Team::query()->lockForUpdate()->findOrFail($team->id);
            $email = mb_strtolower(trim($validated['email']));
            abort_if(
                $lockedTeam->allow_seats <= $lockedTeam->members()->whereIn('status', ['active', 'waiting'])->count(),
                422,
                'This workspace has no available seats.'
            );
            abort_if(
                $lockedTeam->members()->where('email', $email)->exists(),
                422,
                'This email already has an invitation for this workspace.'
            );
            $invitee = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
            abort_if(
                $invitee && $lockedTeam->members()->where('user_id', $invitee->id)->exists(),
                422,
                'This user already belongs to the workspace.'
            );

            $member = $lockedTeam->members()->create([
                'user_id'                 => $invitee?->id,
                'email'                   => $email,
                'role'                    => $validated['role'],
                'status'                  => 'waiting',
                'allow_unlimited_credits' => false,
            ]);

            if ($invitee) {
                $this->createWorkspaceInvitationNotification($invitee, $actor, $lockedTeam, $member);
            }

            return $member;
        });

        if (! $member->user_id) {
            dispatch(new SendTeamInviteEmail($actor, $member->email));
        }

        return response()->json([
            'member' => $this->memberPayload($member->load('user:id,name,surname,email'), true),
        ], 201);
    }

    public function decideInvitation(Request $request, TeamMember $member): JsonResponse
    {
        /** @var User $invitee */
        $invitee = $request->user();
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'declined'])],
        ]);
        abort_unless(
            mb_strtolower((string) $member->email) === mb_strtolower((string) $invitee->email)
                && (! $member->user_id || $member->user_id === $invitee->id),
            404,
            'Workspace invitation not found.'
        );
        abort_unless($member->status === 'waiting', 409, 'This invitation is no longer pending.');

        $member = DB::transaction(function () use ($member, $invitee, $validated): TeamMember {
            $lockedMember = TeamMember::query()->lockForUpdate()->findOrFail($member->id);
            abort_unless(
                mb_strtolower((string) $lockedMember->email) === mb_strtolower((string) $invitee->email)
                    && (! $lockedMember->user_id || $lockedMember->user_id === $invitee->id),
                404,
                'Workspace invitation not found.'
            );
            abort_unless($lockedMember->status === 'waiting', 409, 'This invitation is no longer pending.');

            if ($validated['decision'] === 'accepted') {
                $lockedMember->forceFill([
                    'user_id'   => $invitee->id,
                    'status'    => 'active',
                    'joined_at' => now(),
                ])->save();

                if (! $invitee->team_id) {
                    $invitee->forceFill([
                        'team_id'         => $lockedMember->team_id,
                        'team_manager_id' => $lockedMember->team?->user_id,
                    ])->save();
                }
            } else {
                $lockedMember->forceFill(['status' => 'declined'])->save();
            }

            return $lockedMember->fresh()->load('team');
        });

        $inviteNotification = $invitee->notifications()
            ->where('data->teamInvitationId', (string) $member->id)
            ->first();
        if ($inviteNotification) {
            $inviteNotification->forceFill([
                'data' => array_merge((array) $inviteNotification->data, [
                    'decision' => $member->status === 'active' ? 'accepted' : 'declined',
                ]),
                'read_at' => now(),
            ])->save();
        }

        return response()->json([
            'invitationId' => (string) $member->id,
            'workspaceId'  => (string) $member->team_id,
            'status'       => $member->status === 'active' ? 'accepted' : 'declined',
        ]);
    }

    public function updateMember(Request $request, Team $team, TeamMember $member): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->authorizeTeamManagement($actor, $team);
        abort_unless($member->team_id === $team->id, 404);
        abort_if($member->user_id === $team->user_id, 403, 'The workspace owner role cannot be changed.');
        abort_if(
            $actor->id !== $team->user_id && $member->role === 'admin',
            403,
            'Only the workspace owner can change administrator roles.'
        );

        $validated = $request->validate([
            'role'   => ['required', Rule::in(self::ASSIGNABLE_ROLES)],
            'status' => ['sometimes', Rule::in(['active', 'waiting'])],
        ]);
        abort_if(
            $actor->id !== $team->user_id && $validated['role'] === 'admin',
            403,
            'Only the workspace owner can assign administrators.'
        );

        $member->fill($validated)->save();

        return response()->json([
            'member' => $this->memberPayload($member->fresh()->load('user:id,name,surname,email'), true),
        ]);
    }

    public function destroyMember(Request $request, Team $team, TeamMember $member): JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $this->authorizeTeamManagement($actor, $team);
        abort_unless($member->team_id === $team->id, 404);
        abort_if($member->user_id === $team->user_id, 403, 'The workspace owner cannot be removed.');
        abort_if(
            $actor->id !== $team->user_id && $member->role === 'admin',
            403,
            'Only the workspace owner can remove administrators.'
        );

        DB::transaction(function () use ($member, $team): void {
            if ($member->user_id) {
                $otherMembership = TeamMember::query()
                    ->with('team')
                    ->where('user_id', $member->user_id)
                    ->where('status', 'active')
                    ->where('id', '!=', $member->id)
                    ->first();
                $user = User::query()->find($member->user_id);
                if ($user && (int) $user->team_id === (int) $team->id) {
                    $user->forceFill([
                        'team_id'         => $otherMembership?->team_id,
                        'team_manager_id' => $otherMembership?->team?->user_id,
                    ])->save();
                }
            }
            $member->delete();
        });

        return response()->json(['removed' => true]);
    }

    private function authorizeTeamManagement(User $actor, Team $team): void
    {
        abort_unless(
            $actor->id === $team->user_id
                || $actor->hasWorkspaceRole('team', (int) $team->id, ['admin']),
            403,
            'You do not have permission to manage this workspace.'
        );
    }

    private function teamPayload(Team $team, User $viewer): array
    {
        $isOwner = $team->user_id === $viewer->id;
        $isAdmin = $isOwner || $viewer->hasWorkspaceRole('team', (int) $team->id, ['admin']);
        $viewerMembership = $team->members->first(
            fn (TeamMember $member): bool => $member->user_id === $viewer->id && $member->status === 'active'
        );
        $owner = $team->user;
        $members = $team->members
            ->map(fn (TeamMember $member): array => $this->memberPayload($member, $isAdmin))
            ->prepend([
                'id'       => 'owner-' . $team->id,
                'userId'   => $owner ? (string) $owner->id : null,
                'name'     => $owner ? trim($owner->name . ' ' . $owner->surname) : null,
                'email'    => $isAdmin ? $owner?->email : null,
                'role'     => 'owner',
                'status'   => 'active',
                'joinedAt' => $team->created_at?->toISOString(),
            ]);

        return [
            'id'               => (string) $team->id,
            'name'             => $team->name,
            'role'             => $isOwner ? 'owner' : $viewerMembership?->role,
            'isOwner'          => $isOwner,
            'canManageMembers' => $isAdmin,
            'seatLimit'        => (int) $team->allow_seats,
            'usedSeats'        => (int) $team->members
                ->whereIn('status', ['active', 'waiting'])
                ->count(),
            'members'          => $members->values()->all(),
        ];
    }

    private function memberPayload(TeamMember $member, bool $canViewEmail): array
    {
        return [
            'id'     => (string) $member->id,
            'userId' => $member->user_id ? (string) $member->user_id : null,
            'name'   => $member->user
                ? trim($member->user->name . ' ' . $member->user->surname)
                : null,
            'email'    => $canViewEmail ? $member->email ?? $member->user?->email : null,
            'role'     => $member->role,
            'status'   => $member->status,
            'joinedAt' => $member->joined_at?->toISOString(),
        ];
    }

    private function createWorkspaceInvitationNotification(
        User $recipient,
        User $actor,
        Team $team,
        TeamMember $member,
    ): void {
        $recipient->notifications()->create([
            'id'   => (string) Str::uuid(),
            'type' => 'agent_social',
            'data' => [
                'type'   => 'workspace_invitation',
                'title'  => 'Invitation to ' . $team->name,
                'detail' => $actor->public_username
                    ? '@' . $actor->public_username . ' invited you to join ' . $team->name . ' as ' . $member->role . '.'
                    : $actor->fullName() . ' invited you to join ' . $team->name . ' as ' . $member->role . '.',
                'actorId'          => (string) $actor->id,
                'actorUsername'    => $actor->public_username,
                'workspaceId'      => (string) $team->id,
                'workspaceName'    => $team->name,
                'teamInvitationId' => (string) $member->id,
            ],
        ]);
    }
}
