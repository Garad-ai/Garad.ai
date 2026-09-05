 <x-card
     class="flex flex-col"
     class:body="flex flex-col grow p-0"
 >
     <x-slot:head
         class="flex items-center justify-between px-5 py-4"
     >
         <h4 class="m-0 text-xs font-medium">
             {{ __('Upcoming Tasks') }}
         </h4>
         <x-button
             class="text-[12px] font-medium"
             variant="link"
             href="{{ route('dashboard.user.crm.tasks.index') }}"
         >
             {{ __('View All') }}
             <x-tabler-chevron-right class="size-4 rtl:rotate-180" />
         </x-button>
     </x-slot:head>

     <div class="divide-y">
         @forelse ($upcomingTasks as $task)
             @php
                 $isOverdue = $task->due_date?->isPast() ?? false;
             @endphp
             <div
                 id="dashboard-task-{{ $task->id }}"
                 @class([
                     'task-item group/task flex items-center gap-3 px-5 py-4',
                     'is-overdue' => $isOverdue,
                 ])
             >
                 <button
                     class="inline-grid size-9 place-items-center rounded-full border shadow-xs"
                     type="button"
                     title="{{ __('Mark as completed') }}"
                     onclick="crmCompleteTask({{ $task->id }}, this)"
                 >
                     <span class="inline-grid size-5 place-items-center rounded-full bg-emerald-500 text-white opacity-0 transition group-[&.is-completed]/task:opacity-100">
                         <x-tabler-check class="size-4" />
                     </span>
                 </button>

                 <div class="min-w-0 grow">
                     <p class="m-0 truncate text-xs font-medium text-heading-foreground group-[&.is-completed]/task:line-through">
                         {{ $task->title }}
                     </p>
                     <p class="opacity/40 m-0 truncate text-[12px]">
                         {{ $task->contact?->full_name }}
                     </p>
                 </div>

                 @if ($task->due_date)
                     <span @class([
                         'shrink-0 rounded-full px-2 py-0.5 text-[12px]',
                         'bg-red-500/10 text-red-500' => $isOverdue,
                         'bg-foreground/5' => !$isOverdue,
                     ])>
                         {{ $task->due_date->format('M d') }}
                     </span>
                 @endif
             </div>
         @empty
             <div class="p-5">
                 <x-empty-state
                     icon="tabler-checklist"
                     title="{{ __('No upcoming tasks.') }}"
                     description="{{ __('All tasks are completed.') }}"
                 />
             </div>
         @endforelse
     </div>
 </x-card>

 @push('script')
     <script>
         function handleOverdueSvg({
             increament,
             decreament
         } = {}) {
             const el = document.querySelector('.overdue-svg');
             const elData = Alpine.$data(el);

             if (!elData) return;

             if (increament || decreament) {
                 elData.overdueCount = increament ? (elData.overdueCount + increament) : (elData.overdueCount - decreament);
             }
         }

         function crmCompleteTask(taskId, btn) {
             const row = document.getElementById('dashboard-task-' + taskId);

             if (!row) return;

             const isOverdue = row.classList.contains('is-overdue');
             const isCompleted = row.classList.contains('is-completed');

             row.classList.toggle('is-completed', !isCompleted);

             $.ajax({
                 type: 'POST',
                 url: '{{ route('dashboard.user.crm.tasks.updateStatus') }}',
                 data: {
                     task_id: taskId,
                     status: isCompleted ? 'pending' : 'completed',
                     _token: '{{ csrf_token() }}',
                 },
                 success: function(data) {
                     const completed = data.taskStatus === 'completed';

                     row.classList.toggle('is-completed', completed);

                     isOverdue && handleOverdueSvg({
                         decreament: completed ? 1 : 0,
                         increament: !completed ? 1 : 0,
                     });
                 },
                 error: function() {
                     row.classList.toggle('is-completed', isCompleted);
                     isOverdue && handleOverdueSvg({
                         increament: 1
                     });
                 },
             });
         }
     </script>
 @endpush
