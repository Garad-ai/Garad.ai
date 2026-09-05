@extends('panel.layout.app', ['disable_tblr' => true])
@section('title', __('Manage Skills'))
@section('titlebar_subtitle', __('View and manage public skills.'))
@section('titlebar_actions')
    <x-button
        variant="primary"
        href="javascript:void(0)"
        onclick="document.getElementById('uploadSkillModalTrigger')?.click();"
    >
        <x-tabler-plus class="size-4" />
        {{ __('Add Skill') }}
    </x-button>
@endsection

@section('content')
    <div class="py-10">
        @if ($skills->isEmpty())
            <x-empty-state
                icon="tabler-tool"
                :title="__('No skills yet')"
                :description="__('Upload or import skills to extend AI capabilities for your users.')"
            />
        @else
            <x-table class="table">
                <x-slot:head>
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Added') }}</th>
                        <th>{{ __('Source') }}</th>
                        <th class="text-end">{{ __('Action') }}</th>
                    </tr>
                </x-slot:head>

                <x-slot:body>
                    @foreach ($skills as $skill)
                        <tr>
                            <td>
                                <div class="flex flex-col">
                                    <span class="font-semibold text-heading-foreground">{{ $skill->name }}</span>
                                    <span class="text-2xs text-foreground/60">{{ Str::limit($skill->description, 60) }}</span>
                                </div>
                            </td>
                            <td>
                                <p class="m-0">
                                    {{ $skill->created_at->format('M d, Y, H:i') }}
                                </p>
                            </td>
                            <td>
                                @if ($skill->source === 'seeded')
                                    <span class="inline-flex items-center rounded-md bg-primary/10 px-2 py-1 text-2xs font-medium text-primary">
                                        {{ __('Built-in') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-foreground/5 px-2 py-1 text-2xs font-medium capitalize">
                                        {{ $skill->source }}
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-end">
                                <div class="flex items-center justify-end gap-1">
                                    <x-button
										href="javascript:void(0)"
										class="rounded-full p-2"
                                        size="sm"
                                        variant="ghost-shadow"
                                        onclick="viewSkillDetail({{ $skill->id }})"
                                    >
                                        <x-tabler-eye class="size-4" />
                                    </x-button>

                                    <x-button
										href="javascript:void(0)"
										class="rounded-full p-2"
                                        size="sm"
                                        variant="ghost-shadow"
                                        onclick="editSkill({{ $skill->id }}, {{ json_encode($skill->name) }}, {{ json_encode($skill->description) }}, {{ json_encode($skill->status) }})"
                                    >
                                        <x-tabler-pencil class="size-4" />
                                    </x-button>

                                    @if ($skill->source !== 'seeded')
                                        @if ($app_is_demo)
                                            <x-button
                                                href="javascript:void(0)"
                                                class="rounded-full p-2"
                                                size="sm"
                                                variant="danger"
                                                onclick="return toastr.info('{{ __('This feature is disabled in Demo version.') }}')"
                                            >
                                                <x-tabler-trash class="size-4" />
                                            </x-button>
                                        @else
                                            <x-button
                                                href="javascript:void(0)"
                                                class="rounded-full p-2"
                                                size="sm"
                                                variant="danger"
                                                onclick="deleteSkill({{ $skill->id }})"
                                            >
                                                <x-tabler-trash class="size-4" />
                                            </x-button>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </x-slot:body>
            </x-table>

            <div class="mt-4">
                {{ $skills->links() }}
            </div>
        @endif
    </div>

    {{-- Upload/Import Skill Modal --}}
    <x-modal
        id="uploadSkillModal"
        title="{{ __('Add Skill') }}"
        class:modal="!z-[990]"
    >
        <x-slot:trigger
            class="hidden"
            id="uploadSkillModalTrigger"
        >
            {{ __('Open') }}
        </x-slot:trigger>

        <x-slot:modal>
            <div
                x-data="{
                    activeTab: 'upload',
                    file: null,
                    fileName: '',
                    githubUrl: '',
                    loading: false,
                    dragover: false,
                    scannedFiles: [],
                    showFileList: false,

                    handleFileDrop(e) {
                        this.dragover = false;
                        const files = e.dataTransfer?.files;
                        if (files?.length) {
                            this.file = files[0];
                            this.fileName = files[0].name;
                        }
                    },

                    handleFileSelect(e) {
                        const files = e.target.files;
                        if (files?.length) {
                            this.file = files[0];
                            this.fileName = files[0].name;
                        }
                    },

                    async submitUpload() {
                        if (!this.file) return;
                        this.loading = true;
                        const formData = new FormData();
                        formData.append('file', this.file);
                        formData.append('_token', document.querySelector('input[name=_token]')?.value || '{{ csrf_token() }}');

                        try {
                            const res = await fetch('{{ route('dashboard.admin.skills.store') }}', {
                                method: 'POST',
                                body: formData,
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            const data = await res.json();
                            if (res.ok) {
                                toastr.success(data.message);
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                const errMsg = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
                                toastr.error(errMsg || 'Upload failed.');
                            }
                        } catch (err) {
                            toastr.error('An error occurred.');
                        } finally {
                            this.loading = false;
                        }
                    },

                    async scanGithub() {
                        if (!this.githubUrl) return;
                        this.loading = true;
                        this.scannedFiles = [];
                        this.showFileList = false;
                        try {
                            const res = await fetch('{{ route('dashboard.admin.skills.scan-github') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({ url: this.githubUrl })
                            });
                            const data = await res.json();
                            if (res.ok && data.files?.length) {
                                this.scannedFiles = data.files.map(f => ({ ...f, selected: true }));
                                this.showFileList = true;
                            } else {
                                toastr.error(data.message || '{{ __('No skill files found.') }}');
                            }
                        } catch (err) {
                            toastr.error('An error occurred.');
                        } finally {
                            this.loading = false;
                        }
                    },

                    get selectedCount() {
                        return this.scannedFiles.filter(f => f.selected).length;
                    },

                    toggleAll(checked) {
                        this.scannedFiles.forEach(f => f.selected = checked);
                    },

                    async importSelected() {
                        const selected = this.scannedFiles.filter(f => f.selected).map(f => f.path);
                        if (!selected.length) return;
                        this.loading = true;
                        try {
                            const res = await fetch('{{ route('dashboard.admin.skills.import-github-bulk') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({ url: this.githubUrl, files: selected })
                            });
                            const data = await res.json();
                            if (res.ok) {
                                toastr.success(data.message);
                                if (data.imported > 0) {
                                    setTimeout(() => location.reload(), 1000);
                                }
                            } else {
                                toastr.error(data.message || 'Import failed.');
                            }
                        } catch (err) {
                            toastr.error('An error occurred.');
                        } finally {
                            this.loading = false;
                        }
                    }
                }"
            >
                {{-- Tabs --}}
                <div class="mb-6 flex gap-2 border-b">
                    <button
                        class="px-4 py-2.5 text-xs font-medium transition-colors [&.active]:border-b-2 [&.active]:border-primary [&.active]:text-primary"
                        :class="{ 'active': activeTab === 'upload' }"
                        @click="activeTab = 'upload'"
                        type="button"
                    >
                        {{ __('Upload Your Skill') }}
                    </button>
                    <button
                        class="px-4 py-2.5 text-xs font-medium transition-colors [&.active]:border-b-2 [&.active]:border-primary [&.active]:text-primary"
                        :class="{ 'active': activeTab === 'github' }"
                        @click="activeTab = 'github'"
                        type="button"
                    >
                        {{ __('Import from GitHub') }}
                    </button>
                </div>

                {{-- Upload Tab --}}
                <div x-show="activeTab === 'upload'" x-cloak>
                    <div
                        class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-8 text-center transition-colors"
                        :class="dragover ? 'border-primary bg-primary/5' : 'border-border'"
                        @dragover.prevent="dragover = true"
                        @dragleave.prevent="dragover = false"
                        @drop.prevent="handleFileDrop($event)"
                    >
                        <x-tabler-file-upload class="mb-3 size-10 text-foreground/40" />
                        <p class="m-0 text-sm font-medium text-heading-foreground" x-show="!fileName">
                            {{ __('Drag and drop .skill, .zip, or .md file here') }}
                        </p>
                        <p class="m-0 text-sm font-medium text-heading-foreground" x-show="fileName" x-text="fileName" x-cloak></p>

                        <div class="my-4 flex w-full items-center gap-3">
                            <div class="h-px flex-1 bg-border"></div>
                            <span class="text-2xs text-foreground/60">{{ __('or') }}</span>
                            <div class="h-px flex-1 bg-border"></div>
                        </div>

                        <x-button
                            href="javascript:void(0)"
                            variant="ghost-shadow"
                            size="sm"
                            type="button"
                            @click="$refs.adminFileInput.click()"
                        >
                            {{ __('Browse Files') }}
                        </x-button>
                        <input
                            class="hidden"
                            type="file"
                            accept=".skill,.zip,.md"
                            x-ref="adminFileInput"
                            @change="handleFileSelect($event)"
                        />
                    </div>

                    <ul class="mt-4 flex flex-col gap-1 text-2xs text-foreground/60">
                        <li>{{ __('• Upload a .skill or .zip file containing a SKILL.md, or upload a .md file directly.') }}</li>
                        <li>{{ __('• The SKILL.md should include both a skill name and its description.') }}</li>
                    </ul>

                    <div class="mt-6">
                        <x-button
                            class="w-full"
							href="javascript:void(0)"
                            variant="secondary"
                            @click="submitUpload()"
                            ::disabled="!file || loading"
                        >
                            <span x-show="!loading">{{ __('Add Skill') }}</span>
                            <span x-show="loading" x-cloak>{{ __('Uploading...') }}</span>
                            <x-tabler-chevron-right class="size-4" x-show="!loading" />
                        </x-button>
                    </div>
                </div>

                {{-- GitHub Tab --}}
                <div x-show="activeTab === 'github'" x-cloak>
                    <div x-show="!showFileList">
                        <x-forms.input
                            type="text"
                            label="{{ __('GitHub Repository URL') }}"
                            placeholder="https://github.com/owner/repo or .../blob/main/path/SKILL.md"
                            x-model="githubUrl"
                        />
                        <p class="mt-1 text-2xs text-foreground/50">
                            {{ __('Paste a repo URL to scan for all skill files, or a direct link to a specific .md file.') }}
                        </p>

                        <div class="mt-6">
                            <x-button
                                class="w-full"
								href="javascript:void(0)"
                                variant="secondary"
                                @click="scanGithub()"
                                ::disabled="!githubUrl || loading"
                            >
                                <span x-show="!loading">{{ __('Scan Repository') }}</span>
                                <span x-show="loading" x-cloak>{{ __('Scanning...') }}</span>
                                <x-tabler-chevron-right class="size-4" x-show="!loading" />
                            </x-button>
                        </div>
                    </div>

                    {{-- File selection list --}}
                    <div x-show="showFileList" x-cloak>
                        <div class="mb-3 flex items-center justify-between">
                            <p class="m-0 text-xs font-medium text-heading-foreground">
                                {{ __('Skill files found') }}
                                (<span x-text="selectedCount"></span>/<span x-text="scannedFiles.length"></span> {{ __('selected') }})
                            </p>
                            <button
                                class="text-2xs text-primary hover:underline"
                                type="button"
                                @click="showFileList = false; scannedFiles = []"
                            >
                                {{ __('Back') }}
                            </button>
                        </div>

                        <div class="mb-3 flex items-center gap-2 border-b pb-2">
                            <input
                                class="size-4 rounded border-input-border text-primary accent-primary"
                                type="checkbox"
                                checked
                                @change="toggleAll($event.target.checked)"
                            />
                            <span class="text-2xs font-medium text-foreground/60">{{ __('Select / Deselect All') }}</span>
                        </div>

                        <div class="max-h-[300px] overflow-y-auto">
                            <template x-for="(file, index) in scannedFiles" :key="file.path">
                                <label class="flex cursor-pointer items-start gap-2.5 rounded-lg px-2 py-2 transition-colors hover:bg-foreground/[3%]">
                                    <input
                                        class="mt-0.5 size-4 rounded border-input-border text-primary accent-primary"
                                        type="checkbox"
                                        x-model="file.selected"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <p class="m-0 truncate text-xs font-medium text-heading-foreground" x-text="file.name"></p>
                                        <p class="m-0 truncate text-2xs text-foreground/40" x-text="file.path"></p>
                                    </div>
                                </label>
                            </template>
                        </div>

                        <div class="mt-4">
                            <x-button
                                href="javascript:void(0)"
                                class="w-full"
                                variant="secondary"
                                @click="importSelected()"
                                ::disabled="selectedCount === 0 || loading"
                            >
                                <span x-show="!loading">{{ __('Import Selected') }} (<span x-text="selectedCount"></span>)</span>
                                <span x-show="loading" x-cloak>{{ __('Importing...') }}</span>
                                <x-tabler-chevron-right class="size-4" x-show="!loading" />
                            </x-button>
                        </div>
                    </div>
                </div>
            </div>
        </x-slot:modal>
    </x-modal>

    {{-- Edit Skill Modal --}}
    <x-modal
        id="editSkillModal"
        title="{{ __('Edit Skill') }}"
    >
        <x-slot:trigger
            class="hidden"
            id="editSkillModalTrigger"
        >
            {{ __('Open') }}
        </x-slot:trigger>

        <x-slot:modal>
            <div
                x-data="{
                    skillId: null,
                    name: '',
                    description: '',
                    status: 'active',
                    loading: false,

                    init() {
                        window.addEventListener('edit-skill', (e) => {
                            this.skillId = e.detail.id;
                            this.name = e.detail.name;
                            this.description = e.detail.description;
                            this.status = e.detail.status;
                        });
                    },

                    async save() {
                        this.loading = true;
                        try {
                            const res = await fetch(`/dashboard/admin/skills/${this.skillId}`, {
                                method: 'PUT',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({
                                    name: this.name,
                                    description: this.description,
                                    status: this.status
                                })
                            });
                            const data = await res.json();
                            if (res.ok) {
                                toastr.success(data.message);
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                toastr.error(data.message || 'Update failed.');
                            }
                        } catch (err) {
                            toastr.error('An error occurred.');
                        } finally {
                            this.loading = false;
                        }
                    }
                }"
            >
                <div class="flex flex-col gap-4">
                    <x-forms.input
                        type="text"
                        label="{{ __('Name') }}"
                        x-model="name"
                    />
                    <x-forms.input
                        type="textarea"
                        label="{{ __('Description') }}"
                        x-model="description"
                        rows="3"
                    />
                    <x-forms.input
                        type="select"
                        label="{{ __('Status') }}"
                        x-model="status"
                    >
                        <option value="active">{{ __('Active') }}</option>
                        <option value="inactive">{{ __('Inactive') }}</option>
                    </x-forms.input>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <x-button
                        href="javascript:void(0)"
                        variant="outline"
                        @click="modalOpen = false"
                        type="button"
                    >
                        {{ __('Cancel') }}
                    </x-button>
                    <x-button
                        href="javascript:void(0)"
                        variant="primary"
                        @click="save()"
                        ::disabled="loading"
                    >
                        <span x-show="!loading">{{ __('Save') }}</span>
                        <span x-show="loading" x-cloak>{{ __('Saving...') }}</span>
                    </x-button>
                </div>
            </div>
        </x-slot:modal>
    </x-modal>

    {{-- Skill Detail Modal --}}
    <x-modal
        id="skillDetailModal"
        title="{{ __('Skill Detail') }}"
    >
        <x-slot:trigger
            class="hidden"
            id="skillDetailModalTrigger"
        >
            {{ __('Open') }}
        </x-slot:trigger>

        <x-slot:modal>
            <div
                x-data="{
                    skill: null,
                    loading: false,

                    init() {
                        window.addEventListener('view-skill-detail', async (e) => {
                            this.loading = true;
                            try {
                                const res = await fetch(`/dashboard/admin/skills/${e.detail.id}`);
                                const data = await res.json();
                                this.skill = data.skill;
                            } catch (err) {
                                toastr.error('Failed to load skill details.');
                            } finally {
                                this.loading = false;
                            }
                        });
                    }
                }"
            >
                <div x-show="loading" class="flex items-center justify-center py-8">
                    <x-tabler-loader-2 class="size-6 animate-spin text-primary" />
                </div>

                <div x-show="!loading && skill" x-cloak class="flex gap-6 max-md:flex-col">
                    {{-- Filetree --}}
                    <div class="w-48 shrink-0 rounded-lg border p-3 max-md:w-full">
                        <p class="mb-2 text-2xs font-semibold uppercase tracking-wider text-foreground/60">{{ __('Files') }}</p>
                        <div class="flex flex-col gap-1 text-xs">
                            <div class="flex items-center gap-1.5">
                                <x-tabler-file-text class="size-3.5 text-primary" />
                                <span>SKILL.md</span>
                            </div>
                            <template x-if="skill?.bundled_resources">
                                <template x-for="(files, folder) in skill.bundled_resources" :key="folder">
                                    <div>
                                        <div class="mt-1 flex items-center gap-1.5 font-medium">
                                            <x-tabler-folder class="size-3.5 text-foreground/60" />
                                            <span x-text="folder"></span>
                                        </div>
                                        <template x-for="file in files" :key="file">
                                            <div class="ms-5 flex items-center gap-1.5 text-foreground/60">
                                                <x-tabler-file class="size-3" />
                                                <span x-text="file"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </template>
                        </div>
                    </div>

                    {{-- Content --}}
                    <div class="flex-1">
                        <h4 class="mb-1 text-base font-semibold text-heading-foreground" x-text="skill?.name"></h4>
                        <p class="mb-4 text-xs text-foreground/60" x-text="skill?.description"></p>

                        <div class="rounded-lg border p-4">
                            <p class="mb-2 text-2xs font-semibold uppercase tracking-wider text-foreground/60">{{ __('Instructions') }}</p>
                            <pre class="whitespace-pre-wrap text-xs text-foreground/80" x-text="skill?.instructions"></pre>
                        </div>

                        <div class="mt-3 flex items-center gap-4 text-2xs text-foreground/50">
                            <span>{{ __('Source:') }} <span class="capitalize" x-text="skill?.source"></span></span>
                            <span x-show="skill?.source_url">
                                <a :href="skill?.source_url" target="_blank" class="text-primary hover:underline" x-text="'GitHub'"></a>
                            </span>
                            <span>{{ __('Created:') }} <span x-text="skill?.created_at"></span></span>
                        </div>
                    </div>
                </div>
            </div>
        </x-slot:modal>
    </x-modal>

    @push('script')
        <script>
            function editSkill(id, name, description, status) {
                window.dispatchEvent(new CustomEvent('edit-skill', {
                    detail: { id, name, description, status }
                }));
                document.getElementById('editSkillModalTrigger')?.click();
            }

            function viewSkillDetail(id) {
                window.dispatchEvent(new CustomEvent('view-skill-detail', {
                    detail: { id }
                }));
                document.getElementById('skillDetailModalTrigger')?.click();
            }

            function deleteSkill(id) {
                if (!confirm('{{ __('Are you sure you want to delete this skill?') }}')) return;

                fetch(`/dashboard/admin/skills/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.message) {
                        toastr.success(data.message);
                        setTimeout(() => location.reload(), 1000);
                    }
                })
                .catch(() => toastr.error('An error occurred.'));
            }
        </script>
    @endpush
@endsection
