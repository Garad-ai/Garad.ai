@php
    use App\Helpers\Classes\MarketplaceHelper;
    $disable_actions = $app_is_demo ?? false;
    $is_search = $is_search ?? false;
    $categoryId = isset($category) ? $category?->id : null;
    $website_url = $website_url ?? 'chatpro';
    $isChatProImage = false;
    $isChatProContext = true;
    $chatProFoldersEnabled = MarketplaceHelper::isRegistered('ai-chat-pro-folders');
@endphp

<div
    class="chat-list-wrapper garad-unified-chat-list h-full min-h-0"
    x-data="chatSidebarManager({
        categoryId: {{ $categoryId ?? 'null' }},
        isSearch: false,
        websiteUrl: 'chatpro',
        foldersEnabled: {{ $chatProFoldersEnabled ? 'true' : 'false' }},
        isChatProImage: false,
        disableActions: {{ $disable_actions ? 'true' : 'false' }}
    })"
>
    <div class="flex flex-col gap-2 px-3 py-2" x-show="initialLoading" x-cloak>
        <template x-for="i in 8"><div class="h-8 animate-pulse rounded-lg bg-foreground/[.05]"></div></template>
    </div>

    <div class="garad-sidebar-scroll h-full overflow-y-auto px-2 pb-5" x-show="!initialLoading" x-cloak>
        {{-- Search results stay inside the same single sidebar. --}}
        <section class="garad-sidebar-group" x-show="isSearch">
            <div class="garad-sidebar-section-label">{{ __('Search results') }}</div>
            <template x-for="chat in searchResults" :key="'search-' + chat.id">
                @include('ai-chat-pro::includes.sidebar-chat-row')
            </template>
            <div class="garad-sidebar-empty" x-show="searchResults.length === 0 && !fetchingChats">{{ __('No chats found') }}</div>
        </section>

        <template x-if="!isSearch">
            <div>
                {{-- When a project is opened, keep it inside the same sidebar rather than replacing the whole navigation column. --}}
                <section class="garad-sidebar-group" x-show="openFolder !== null">
                    <button type="button" class="garad-project-back" @click="toggleFolder(null)">
                        <x-tabler-arrow-left class="size-4" /> <span>{{ __('Back') }}</span>
                    </button>
                    <div class="garad-sidebar-section-label garad-project-open-label">
                        <span x-text="folders.find(folder => folder.id === openFolder)?.name || '{{ __('Project') }}'"></span>
                    </div>
                    <template x-for="chat in projectChats" :key="'project-chat-' + chat.id">
                        @include('ai-chat-pro::includes.sidebar-chat-row')
                    </template>
                    <div class="garad-sidebar-empty" x-show="projectChats.length === 0">{{ __('No chats in this project') }}</div>
                </section>

                <div x-show="openFolder === null">
                    {{-- PROJECTS --}}
                    @if ($chatProFoldersEnabled)
                        <section class="garad-sidebar-group">
                            <div class="garad-sidebar-section-head">
                                <div class="garad-sidebar-section-label">{{ __('Projects') }}</div>
                                <button type="button" class="garad-sidebar-section-action" title="{{ __('New project') }}" @click.prevent="toggleAddFolderModal(true)">
                                    <x-tabler-plus class="size-4" />
                                </button>
                            </div>
                            <template x-for="folder in folders" :key="'project-' + folder.id">
                                <div class="garad-project-row group">
                                    <button type="button" class="garad-project-open" @click="toggleFolder(folder.id)">
                                        <x-tabler-folder class="size-4 shrink-0 opacity-75" />
                                        <span class="truncate" x-text="folder.name"></span>
                                    </button>
                                    <x-dropdown.dropdown class="shrink-0" offsetY="8px">
                                        <x-slot:trigger class="garad-chat-row-menu inline-grid size-7 place-items-center rounded-md p-0 opacity-0 group-hover:opacity-100 group-[&.lqd-is-active]/dropdown:opacity-100" variant="none">
                                            <x-tabler-dots class="size-4 opacity-70" />
                                        </x-slot:trigger>
                                        <x-slot:dropdown class="min-w-[170px] p-2">
                                            <x-button class="w-full justify-start rounded-md px-3 py-2 text-2xs" variant="none" @click.prevent.stop="toggle('collapse'); setSelectedItem({type: 'folder', id: folder.id, title: folder.name}); toggleRenameModal(true);">
                                                <x-tabler-pencil class="size-4" /> {{ __('Rename') }}
                                            </x-button>
                                            <x-button class="w-full justify-start rounded-md px-3 py-2 text-2xs text-red-500" variant="none" @click.prevent.stop="toggle('collapse'); setSelectedItem({type: 'folder', id: folder.id, title: folder.name}); toggleDeleteModal(true);">
                                                <x-tabler-trash class="size-4" /> {{ __('Delete') }}
                                            </x-button>
                                        </x-slot:dropdown>
                                    </x-dropdown.dropdown>
                                </div>
                            </template>
                            <button type="button" class="garad-project-add" @click.prevent="toggleAddFolderModal(true)">
                                <x-tabler-folder-plus class="size-4" /> <span>{{ __('New project') }}</span>
                            </button>
                        </section>
                    @endif

					{{-- PINNED --}}
					<section class="garad-sidebar-group" x-show="pinnedChats.length > 0">
						<div class="garad-sidebar-section-label">{{ __('Pinned') }}</div>
						<template x-for="chat in pinnedChats" :key="'pinned-' + chat.id">
							@include('ai-chat-pro::includes.sidebar-chat-row')
						</template>
					</section>

                    {{-- CHATS --}}
                    <section class="garad-sidebar-group">
                        <div class="garad-sidebar-section-label">{{ __('Chats') }}</div>
                        <template x-for="chat in regularChats" :key="'chat-' + chat.id">
                            @include('ai-chat-pro::includes.sidebar-chat-row')
                        </template>
                        <div class="garad-sidebar-empty" x-show="regularChats.length === 0 && !fetchingChats">{{ __('No chats yet') }}</div>
                    </section>
                </div>
            </div>
        </template>

        <div class="flex justify-center py-3" x-show="loadingMore || fetchingChats" x-cloak>
            <x-tabler-loader-2 class="size-4 animate-spin opacity-50" />
        </div>
    </div>

	@if ($chatProFoldersEnabled)
		{{-- Add Folder Modal --}}
		<x-modal
			class="chat-add-folder-modal"
			class:modal-content="w-[min(calc(100%-2rem),600px)] lg:overflow-visible"
			class:modal-backdrop="bg-black/40"
			class:modal-head="lg:border-none lg:p-0"
			class:modal-body="px-7 py-10"
			class:close-btn="lg:absolute lg:top-0 lg:-end-12 lg:bg-background lg:rounded-full lg:size-[35px] lg:hover:bg-background lg:hover:scale-105"
			x-init="$watch('modalOpen', open => $nextTick(changeSidebarLock(open)))"
		>
			<x-slot:modal>
				<form
					class="space-y-7"
					@submit.prevent="createFolder"
				>
					<div>
						<h3 class="mb-3">
							@lang('New Project')
						</h3>
						<p class="mb-0 text-balance">
							@lang('Create a project to keep related conversations together.')
						</p>
					</div>

					<x-forms.input
						class:label="font-medium text-xs"
						label="{{ __('Name') }}"
						placeholder="Work"
						size="lg"
						name="folderName"
					/>

					<x-button
						class="w-full"
						variant="secondary"
						::disabled="loading"
						type="submit"
					>
						@lang('Create Project')
						<span class="inline-grid size-7 place-items-center rounded-full bg-background text-foreground">
                            <x-tabler-chevron-right
								class="size-4"
								x-show="!loading"
							/>
                            <x-tabler-loader-2
								class="size-4 animate-spin"
								x-cloak
								x-show="loading"
							/>
                        </span>
					</x-button>
				</form>
			</x-slot:modal>
		</x-modal>
	@endif

	{{-- Rename Modal --}}
	<x-modal
		class="chat-rename-modal"
		class:modal-content="w-[min(calc(100%-2rem),600px)] lg:overflow-visible"
		class:modal-backdrop="bg-black/40"
		class:modal-head="lg:border-none lg:p-0"
		class:modal-body="px-7 py-10"
		class:close-btn="lg:absolute lg:top-0 lg:-end-12 lg:bg-background lg:rounded-full lg:size-[35px] lg:hover:bg-background lg:hover:scale-105"
		x-init="$watch('modalOpen', open => $nextTick(changeSidebarLock(open)))"
	>
		<x-slot:modal>
			<form
				class="space-y-7"
				@submit.prevent="renameItem"
			>
				<div>
					<h3 class="mb-3">
						@lang('Renaming')
						<span
							class="underline underline-offset-4 opacity-60"
							x-text="selectedItem.type === 'folder' ? folders.find(folder => folder.id === selectedItem.id)?.name : chatsList.find(chat => chat.id === selectedItem.id)?.title"
						></span>
					</h3>
					<p
						class="m-0 text-balance"
						x-text="selectedItem.type == 'folder' ? `{{ __('Enter a new name for the folder.') }}` : `{{ __('Enter a new name for the chat.') }}`"
					></p>
				</div>

				<x-forms.input
					class:label="font-medium text-xs"
					label="{{ __('Name') }}"
					size="lg"
					name="title"
					x-model="selectedItem.title"
				/>

				<input
					type="hidden"
					name="type"
					x-model="selectedItem.type"
				>
				<input
					type="hidden"
					name="id"
					x-model="selectedItem.id"
				>

				<x-button
					class="w-full"
					variant="secondary"
					::disabled="loading"
					type="submit"
				>
					@lang('Rename')
					<span class="inline-grid size-7 place-items-center rounded-full bg-background text-foreground">
                        <x-tabler-chevron-right
							class="size-4"
							x-show="!loading"
						/>
                        <x-tabler-loader-2
							class="size-4 animate-spin"
							x-cloak
							x-show="loading"
						/>
                    </span>
				</x-button>
			</form>
		</x-slot:modal>
	</x-modal>

	{{-- Delete Modal --}}
	<x-modal
		class="chat-delete-modal"
		class:modal-content="w-[min(calc(100%-2rem),600px)] lg:overflow-visible"
		class:modal-backdrop="bg-black/40"
		class:modal-head="lg:border-none lg:p-0"
		class:modal-body="px-7 py-10"
		class:close-btn="lg:absolute lg:top-0 lg:-end-12 lg:bg-background lg:rounded-full lg:size-[35px] lg:hover:bg-background lg:hover:scale-105"
		x-init="$watch('modalOpen', open => $nextTick(changeSidebarLock(open)))"
	>
		<x-slot:modal>
			<form
				class="space-y-7"
				@submit.prevent="deleteItem"
			>
				<div>
					<h3 class="mb-3">
						@lang('Deleting')
						<span
							class="underline underline-offset-4 opacity-60"
							x-text="selectedItem.title"
						></span>
					</h3>
					@if ($chatProFoldersEnabled)
						<p
							class="m-0 text-balance"
							x-cloak
							x-show="selectedItem.type == 'folder'"
						>
							@lang('All chats inside this folder will be moved to the main chat list. This action cannot be undone.')
						</p>
					@endif
					<p
						class="m-0 text-balance"
						x-cloak
						x-show="selectedItem.type == 'chatItem'"
					>
						@lang('This chat will be permanently deleted. This action cannot be undone.')
					</p>
				</div>

				<input
					type="hidden"
					name="type"
					x-model="selectedItem.type"
				>
				<input
					type="hidden"
					name="id"
					x-model="selectedItem.id"
				>

				<div class="flex gap-2">
					<x-button
						class="w-full"
						size="lg"
						variant="outline"
						::disabled="loading"
						type="button"
						@click.prevent="setSelectedItem({type: null, id: null, title: null}); toggleDeleteModal(false);"
					>
						@lang('Cancel')
					</x-button>

					<x-button
						class="w-full"
						variant="danger"
						::disabled="loading"
						type="submit"
					>
						@lang('Delete')
						<span class="inline-grid size-7 place-items-center rounded-full bg-background text-foreground">
                            <x-tabler-trash
								class="size-4"
								x-show="!loading"
							/>
                            <x-tabler-loader-2
								class="size-4 animate-spin"
								x-cloak
								x-show="loading"
							/>
                        </span>
					</x-button>
				</div>
			</form>
		</x-slot:modal>
	</x-modal>
</div>

@pushOnce('script')
	<script>
		document.addEventListener('alpine:init', () => {
			Alpine.data('chatSidebarManager', ({
												   categoryId = null,
												   isSearch = false,
												   websiteUrl = '',
												   foldersEnabled = false,
												   isChatProImage = false,
												   disableActions = false
											   }) => ({
				chatsList: [],
				_folders: [],
				openFolder: null,
				openChat: typeof chatid !== 'undefined' ? chatid : null,
				selectedItem: {
					type: 'chatItem',
					id: null,
					title: null
				},

				loading: false,
				initialLoading: true,
				categoryId: categoryId,
				isSearch: isSearch,
				websiteUrl: websiteUrl,
				foldersEnabled: foldersEnabled,
				isChatProImage: isChatProImage,
				disableActions: disableActions,
				searchQuery: '',
				searchDebounceTimer: null,

				pagination: {
					currentPage: 1,
					lastPage: 1,
					perPage: 100,
					total: 0,
					hasMore: false
				},
				loadingMore: false,
				fetchingChats: false,

				async init() {
					if (this.foldersEnabled && !this.isSearch) {
						await this.fetchFolders();
					}

					await this.fetchChats();
					this.initialLoading = false;

					this.$nextTick(() => {
						this.setupInfiniteScroll();
						this.setupSearchListener();
						this.setupSidebarRefreshListener();
					});
				},

				setupInfiniteScroll() {
					const scrollContainer = this.$el.querySelector('.garad-sidebar-scroll');
					if (!scrollContainer) return;

					this.scrollContainer = scrollContainer;

					scrollContainer.addEventListener('scroll', () => {
						this.checkAndLoadMore();
					});

					this.$nextTick(() => {
						this.checkAndLoadMore();
					});
				},

				setupSearchListener() {
					const searchInput = document.querySelector('#chat_search_word');
					if (!searchInput) return;

					searchInput.addEventListener('input', (e) => {
						clearTimeout(this.searchDebounceTimer);
						this.searchDebounceTimer = setTimeout(() => {
							this.searchQuery = e.target.value.trim();
							this.isSearch = this.searchQuery !== '';
							this.pagination.currentPage = 1;
							this.pagination.hasMore = false;
							this.fetchChats(false);
						}, 300);
					});

					// Override global searchChatFunction so the old jQuery handler becomes a no-op
					if (typeof window.searchChatFunction === 'function') {
						window._originalSearchChatFunction = window.searchChatFunction;
						window.searchChatFunction = () => {};
					}
				},

				setupSidebarRefreshListener() {
					document.addEventListener('chat-sidebar:refresh', () => {
						this.pagination.currentPage = 1;
						this.pagination.hasMore = false;
						this.fetchChats(false);
						if (this.foldersEnabled) {
							this.fetchFolders();
						}
					});

					document.addEventListener('chat-sidebar:set-active', (e) => {
						if (e.detail?.chatId) {
							this.openChat = e.detail.chatId;
						}
					});
				},

				checkAndLoadMore() {
					if (this.loadingMore || !this.pagination.hasMore || !this.scrollContainer) return;

					const {
						scrollTop,
						scrollHeight,
						clientHeight
					} = this.scrollContainer;

					const nearBottom = scrollTop + clientHeight >= scrollHeight - 100;
					const contentDoesntFill = scrollHeight <= clientHeight;

					if (nearBottom || contentDoesntFill) {
						this.loadMore();
					}
				},

				get folders() {
					return this._folders.sort((a, b) => a.name.localeCompare(b.name));
				},

				set folders(folders) {
					this._folders = folders;
				},

				sortChats(items) {
					return [...items].sort((a, b) => new Date(b.updated_at || b.created_at) - new Date(a.updated_at || a.created_at));
				},

				get searchResults() {
					return this.sortChats(this.chatsList);
				},

				get pinnedChats() {
					return this.sortChats(this.chatsList.filter(chat => !!chat.is_pinned));
				},

				get regularChats() {
					return this.sortChats(this.chatsList.filter(chat => !chat.is_pinned && (!this.foldersEnabled || !chat.folder_id)));
				},

				get projectChats() {
					if (!this.foldersEnabled || this.openFolder === null) return [];
					return this.sortChats(this.chatsList.filter(chat => chat.folder_id == this.openFolder));
				},

				get chatsListToShow() {
					return this.isSearch ? this.searchResults : (this.openFolder !== null ? this.projectChats : this.regularChats);
				},

				async fetchFolders() {
					try {
						const response = await this.makeRequest('/dashboard/user/ai-chat-pro/folders', {
							method: 'GET'
						});

						if (response.success) {
							this._folders = response.folders;
						}
					} catch (error) {
						console.error('Fetch folders error:', error);
					}
				},

				async fetchChats(append = false) {
					if (this.loadingMore || this.fetchingChats) return;

					const page = append ? this.pagination.currentPage + 1 : 1;

					try {
						this.fetchingChats = true;
						if (append) {
							this.loadingMore = true;
						}

						const params = new URLSearchParams({
							per_page: this.pagination.perPage,
							page: page,
							scope: 'sidebar_all'
						});

						if (this.categoryId) {
							params.append('category_id', this.categoryId);
						}

						if (this.websiteUrl) {
							params.append('website_url', this.websiteUrl);
						}

						if (this.searchQuery) {
							params.append('search', this.searchQuery);
						}


						const response = await this.makeRequest(
							`${window.garadAppBase}/dashboard/user/openai/chat/get-chats?${params.toString()}`, {
								method: 'GET'
							}
						);

						if (response.success) {
							if (append) {
								const existingIds = new Set(this.chatsList.map(c => c.id));
								const newChats = response.chats.filter(c => !existingIds.has(c.id));
								this.chatsList = [...this.chatsList, ...newChats];
							} else {
								this.chatsList = response.chats;
							}

							this.pagination = {
								currentPage: response.pagination.current_page,
								lastPage: response.pagination.last_page,
								perPage: response.pagination.per_page,
								total: response.pagination.total,
								hasMore: response.pagination.has_more
							};
						}
					} catch (error) {
						console.error('Fetch chats error:', error);
					} finally {
						this.loadingMore = false;
						this.fetchingChats = false;
					}
				},

				async loadMore() {
					if (!this.pagination.hasMore || this.loadingMore) return;
					await this.fetchChats(true);

					this.$nextTick(() => {
						this.checkAndLoadMore();
					});
				},

				setSelectedItem({
									type,
									id,
									title
								}) {
					this.selectedItem = {
						type,
						id,
						title
					};
				},

				async ensureAllChatsLoaded() {
					let safety = 0;
					while (this.pagination.hasMore && safety < 10) {
						safety++;
						await this.loadMore();
					}
				},

				async toggleFolder(id) {
					if (!this.foldersEnabled) return;
					this.openFolder = id;
					if (id !== null) {
						await this.ensureAllChatsLoaded();
					}
					this.$nextTick(() => {
						this.scrollContainer?.scrollTo({ top: 0, behavior: 'smooth' });
					});
				},

				toggleAddFolderModal(open, chatId = null) {
					if (!this.foldersEnabled) return;

					const modalEl = document.querySelector('.chat-add-folder-modal');

					if (!modalEl) {
						return toastr.error('{{ __('Could not find the modal element.') }}');
					}

					if (chatId) {
						this.setSelectedItem({
							type: 'chatItem',
							id: chatId,
							title: null
						});
					} else {
						this.setSelectedItem({
							type: null,
							id: null,
							title: null
						});
					}

					Alpine.$data(modalEl).modalOpen = open != null ? open : !Alpine.$data(modalEl).modalOpen;
				},

				toggleRenameModal(open) {
					const modalEl = document.querySelector('.chat-rename-modal');

					if (!modalEl) {
						return toastr.error('{{ __('Could not find the modal element.') }}');
					}

					Alpine.$data(modalEl).modalOpen = open != null ? open : !Alpine.$data(modalEl).modalOpen;
				},

				toggleDeleteModal(open) {
					const modalEl = document.querySelector('.chat-delete-modal');

					if (!modalEl) {
						return toastr.error('{{ __('Could not find the modal element.') }}');
					}

					Alpine.$data(modalEl).modalOpen = open != null ? open : !Alpine.$data(modalEl).modalOpen;
				},

				updateChatItem(chatId, options) {
					this.chatsList = this.chatsList.map(chat => {
						if (chat.id == chatId) {
							Object.keys(options).forEach(key => {
								chat[key] = options[key];
							});
						}
						return chat;
					});
				},

				updateFolder(folderId, options) {
					this._folders = this._folders.map(folder => {
						if (folder.id == folderId) {
							Object.keys(options).forEach(key => {
								folder[key] = options[key];
							});
						}
						return folder;
					});
				},

				async createFolder(event) {
					if (!this.foldersEnabled) return;

					const formData = new FormData(event.target);
					const folderName = formData.get('folderName')?.trim() || '';

					if (!folderName) {
						toastr.error('{{ __('Please enter a folder name') }}');
						return;
					}

					if (this.folders.some(f => f.name.toLowerCase() === folderName.toLowerCase())) {
						toastr.error('{{ __('A folder with this name already exists') }}');
						return;
					}

					this.loading = true;

					try {
						const response = await this.makeRequest('/dashboard/user/ai-chat-pro/folders', {
							method: 'POST',
							body: JSON.stringify({
								name: folderName
							})
						});

						if (response.success) {
							toastr.success(response.message || '{{ __('Project created successfully') }}');
							this._folders.push(response.folder);

							const addFolderModal = document.querySelector('.chat-add-folder-modal');

							if (addFolderModal) {
								Alpine.$data(addFolderModal).modalOpen = false;
							}

							event.target.reset();

							if (this.selectedItem.type === 'chatItem' && this.selectedItem.id) {
								await this.moveToFolder(this.selectedItem.id, response.folder.id);
							}
						}
					} catch (error) {
						console.error('Create folder error:', error);
						toastr.error(error.message || '{{ __('Failed to create folder') }}');
					} finally {
						this.loading = false;
					}
				},

				async renameItem(event) {
					const formData = new FormData(event.target);
					const id = formData.get('id');
					const type = formData.get('type');
					const title = formData.get('title');

					if (type === 'folder') {
						await this.renameFolder(id, title);
					} else if (type === 'chatItem') {
						await this.renameChatItem(id, title);
					}
				},

				async renameFolder(folderId, folderName) {
					if (!this.foldersEnabled || !folderId || !folderName) {
						return toastr.error('{{ __('Please fill all required fields.') }}');
					}

					if (this.folders.some(f =>
						f.id != folderId &&
						f.name.toLowerCase() === folderName.toLowerCase()
					)) {
						return toastr.error('{{ __('A folder with this name already exists') }}');
					}

					this.loading = true;

					try {
						const response = await this.makeRequest(
							`/dashboard/user/ai-chat-pro/folders/${folderId}`, {
								method: 'PUT',
								body: JSON.stringify({
									name: folderName
								})
							}
						);

						if (response.success) {
							toastr.success(response.message || '{{ __('Project renamed successfully') }}');

							this.updateFolder(folderId, {
								name: response.folder.name
							});

							const renameModal = document.querySelector('.chat-rename-modal');

							if (renameModal) {
								Alpine.$data(renameModal).modalOpen = false;
							}
						}
					} catch (error) {
						console.error('Rename folder error:', error);
						toastr.error(error.message || '{{ __('Failed to rename folder') }}');
					} finally {
						this.loading = false;
					}
				},

				async renameChatItem(chatId, title) {
					if (this.disableActions) {
						return toastr.info('{{ __('This feature is disabled in Demo version.') }}');
					}

					if (!chatId || !title) {
						return toastr.error('{{ __('Please fill all required fields.') }}');
					}

					this.loading = true;

					try {
						const response = await this.makeRequest(
							`${window.garadAppBase}/dashboard/user/openai/chat/rename-chat`, {
								method: 'POST',
								body: JSON.stringify({
									chat_id: `chat_${chatId}`,
									title
								})
							}
						);

						if (response.success) {
							toastr.success(response.message || '{{ __('Chat renamed successfully') }}');

							this.updateChatItem(chatId, {
								title
							});

							const renameModal = document.querySelector('.chat-rename-modal');

							if (renameModal) {
								Alpine.$data(renameModal).modalOpen = false;
							}
						}
					} catch (error) {
						console.error('Rename chat error:', error);
						toastr.error(error.message || '{{ __('Failed to rename chat') }}');
					} finally {
						this.loading = false;
					}
				},

				async deleteItem(event) {
					const formData = new FormData(event.target);
					const id = formData.get('id');
					const type = formData.get('type');

					if (type === 'folder') {
						await this.deleteFolder(id);
					} else if (type === 'chatItem') {
						await this.deleteChatItem(id);
					}
				},

				async deleteFolder(folderId) {
					if (!this.foldersEnabled || !folderId) {
						return toastr.error('{{ __('Please fill all required fields.') }}');
					}

					this.loading = true;

					try {
						const response = await this.makeRequest(
							`/dashboard/user/ai-chat-pro/folders/${folderId}`, {
								method: 'DELETE'
							}
						);

						if (response.success) {
							toastr.success(response.message || '{{ __('Project deleted successfully') }}');

							this.chatsList = this.chatsList.map(chat => {
								if (chat.folder_id == folderId) {
									return {
										...chat,
										folder_id: null
									};
								}
								return chat;
							});

							this._folders = this._folders.filter(folder => folder.id != folderId);

							this.openFolder = null;
							await this.fetchChats(false);

							const deleteModal = document.querySelector('.chat-delete-modal');

							if (deleteModal) {
								Alpine.$data(deleteModal).modalOpen = false;
							}
						}
					} catch (error) {
						console.error('Delete folder error:', error);
						toastr.error(error.message || '{{ __('Failed to delete folder') }}');
					} finally {
						this.loading = false;
					}
				},

				async deleteChatItem(chatId) {
					if (this.disableActions) {
						return toastr.info('{{ __('This feature is disabled in Demo version.') }}');
					}

					if (!chatId) {
						return toastr.error('{{ __('Please fill all required fields.') }}');
					}

					this.loading = true;

					try {
						const response = await this.makeRequest(
							`${window.garadAppBase}/dashboard/user/openai/chat/delete-chat`, {
								method: 'POST',
								body: JSON.stringify({
									chat_id: `chat_${chatId}`
								})
							}
						);

						if (response.success) {
							toastr.success(response.message || '{{ __('Chat deleted successfully') }}');

							this.chatsList = this.chatsList.filter(chat => chat.id != chatId);

							const firstChatItem = this.chatsListToShow[0];

							if (firstChatItem) {
								const chatItemEl = document.querySelector(`#chat_${firstChatItem.id}`);

								if (chatItemEl) {
									chatItemEl.querySelector('.chat-list-item-trigger').click();
								}
							}

							const deleteModal = document.querySelector('.chat-delete-modal');

							if (deleteModal) {
								Alpine.$data(deleteModal).modalOpen = false;
							}
						}
					} catch (error) {
						console.error('Delete chat error:', error);
						toastr.error(error.message || '{{ __('Failed to delete chat') }}');
					} finally {
						this.loading = false;
					}
				},

				async moveToFolder(chatId, folderId) {
					if (!this.foldersEnabled) return;

					try {
						const response = await this.makeRequest(
							`/dashboard/user/ai-chat-pro/chats/${chatId}/move-to-folder`, {
								method: 'POST',
								body: JSON.stringify({
									folder_id: folderId
								})
							}
						);

						if (response.success) {
							toastr.success(response.message || '{{ __('Chat moved successfully') }}');

							this.updateChatItem(chatId, {
								folder_id: folderId
							});

						}
					} catch (error) {
						console.error('Move chat error:', error);
						toastr.error(error.message || '{{ __('Failed to move chat') }}');
					}
				},

				async pinChat(chatId, pinned) {
					if (this.disableActions) {
						return toastr.info('{{ __('This feature is disabled in Demo version.') }}');
					}

					if (!chatId) {
						return toastr.error('{{ __('Please fill all required fields.') }}');
					}

					this.loading = true;

					try {
						const response = await this.makeRequest(`${window.garadAppBase}/dashboard/user/openai/chat/pin-conversation`, {
							method: 'POST',
							body: JSON.stringify({
								chat_id: `chat_${chatId}`,
								pinned
							})
						});

						if (response.success) {
							toastr.success(response.message || '{{ __('Chat pin changed successfully.') }}');

							this.updateChatItem(chatId, {
								is_pinned: pinned
							});
						}
					} catch (error) {
						console.error('Pin chat error:', error);
						toastr.error(error.message || '{{ __('Failed to pin chat') }}');
					} finally {
						this.loading = false;
					}
				},

				async makeRequest(url, options = {}) {
					const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

					if (!csrfToken) {
						throw new Error('CSRF token not found');
					}

					const response = await fetch(url, {
						...options,
						headers: {
							'Content-Type': 'application/json',
							'Accept': 'application/json',
							'X-CSRF-TOKEN': csrfToken,
							...options.headers,
						}
					});

					const data = await response.json();

					if (!response.ok) {
						throw new Error(data.message || 'Request failed');
					}

					return data;
				},

				changeSidebarLock(lock = false) {
					const chatsV2Container = document.querySelector('.lqd-chat-v2-container');

					if (!chatsV2Container) return;

					Alpine.$data(chatsV2Container).sidebarLocked = lock;
				},

				getFormattedDateTime(date) {
					const d = new Date(date);
					return d.toLocaleDateString(undefined, {
						year: 'numeric',
						month: 'short',
						day: 'numeric',
						hour: '2-digit',
						minute: '2-digit',
						hour12: false
					});
				}
			}));
		});
	</script>
@endPushOnce
