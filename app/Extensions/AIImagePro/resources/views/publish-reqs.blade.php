@extends('panel.layout.app')
@section('title', __('Publish Requests'))
@section('titlebar_actions', '')

@section('content')
	<div class="py-10" x-data="publishRequestsManager()">
		{{-- Header Stats --}}
		<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
			<x-card class="h-full" size="sm" variant="outline">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-foreground/60 mb-1">{{__('Pending Requests')}}</p>
						<p class="text-3xl font-bold text-foreground" x-text="stats.pending"></p>
					</div>
					<div class="w-12 h-12 rounded-full bg-yellow-500/10 flex items-center justify-center flex-shrink-0">
						<x-tabler-clock class="w-6 h-6 text-yellow-500" />
					</div>
				</div>
			</x-card>
			<x-card class="h-full" size="sm" variant="outline">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-foreground/60 mb-1">{{__('Approved Today')}}</p>
						<p class="text-3xl font-bold text-foreground" x-text="stats.approved_today"></p>
					</div>
					<div class="w-12 h-12 rounded-full bg-green-500/10 flex items-center justify-center flex-shrink-0">
						<x-tabler-check class="w-6 h-6 text-green-500" />
					</div>
				</div>
			</x-card>
			<x-card class="h-full" size="sm" variant="outline">
				<div class="flex items-center justify-between">
					<div>
						<p class="text-sm text-foreground/60 mb-1">{{__('Rejected Today')}}</p>
						<p class="text-3xl font-bold text-foreground" x-text="stats.rejected_today"></p>
					</div>
					<div class="w-12 h-12 rounded-full bg-red-500/10 flex items-center justify-center flex-shrink-0">
						<x-tabler-x class="w-6 h-6 text-red-500" />
					</div>
				</div>
			</x-card>
		</div>

		{{-- Filters --}}
		<x-card class="mb-6" size="sm" variant="outline">
			<div class="flex flex-col md:flex-row gap-3 items-start md:items-center justify-between">
				<div class="flex flex-wrap gap-2 w-full md:w-auto">
					<x-button
						@click="filterStatus = 'all'; loadRequests()"
						::class="filterStatus === 'all' ? 'bg-primary text-primary-foreground' : ''"
						size="sm"
						variant="outline"
					>
						{{__('All')}} (<span x-text="stats.total"></span>)
					</x-button>
					<x-button
						@click="filterStatus = 'pending'; loadRequests()"
						::class="filterStatus === 'pending' ? 'bg-primary text-primary-foreground' : ''"
						size="sm"
						variant="outline"
					>
						{{__('Pending')}} (<span x-text="stats.pending"></span>)
					</x-button>
					<x-button
						@click="filterStatus = 'approved'; loadRequests()"
						::class="filterStatus === 'approved' ? 'bg-primary text-primary-foreground' : ''"
						size="sm"
						variant="outline"
					>
						{{__('Approved')}} (<span x-text="stats.approved"></span>)
					</x-button>
					<x-button
						@click="filterStatus = 'rejected'; loadRequests()"
						::class="filterStatus === 'rejected' ? 'bg-primary text-primary-foreground' : ''"
						size="sm"
						variant="outline"
					>
						{{__('Rejected')}} (<span x-text="stats.rejected"></span>)
					</x-button>
				</div>

				<x-button
					@click="loadRequests()"
					variant="ghost"
					size="sm"
					title="{{__('Refresh')}}"
					class="self-end md:self-auto"
				>
					<x-tabler-refresh class="w-5 h-5" />
				</x-button>
			</div>
		</x-card>

		{{-- Loading State --}}
		<div x-show="loading" class="flex justify-center items-center py-20">
			<div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
		</div>

		{{-- Requests Grid with x-card --}}
		<div
			x-show="!loading && requests.length > 0"
			class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 w-full"
		>
			<template x-for="request in requests" :key="request.id">
				<x-card class="h-full overflow-hidden" variant="shadow">
					<div class="hover:shadow-2xl transition-shadow">
						{{-- Image Preview with Modal --}}
						<div class="relative aspect-square bg-foreground/5 group">
							<img
								:src="request.image_url"
								:alt="request.title"
								class="w-full h-full object-cover rounded-t-lg"
								loading="lazy"
							>
							<div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
								<x-modal
									::id="'previewModal-' + request.id"
									class="lqd-preview-modal"
									class:modal-head="border-b-0"
								>
									<x-slot:trigger
										class="flex size-12 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm hover:bg-white/30 transition-all cursor-pointer"
										variant="none"
									>
										<svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/>
										</svg>
									</x-slot:trigger>
									<x-slot:modal>
										<div class="flex flex-col md:flex-row overflow-hidden max-h-[70vh]">
											{{-- Image Section --}}
											<div class="flex-1 bg-foreground/5 flex items-center justify-center p-4 md:p-8 overflow-auto">
												<img
													:src="request.image_url"
													:alt="request.title"
													class="max-w-full max-h-full object-contain rounded-lg shadow-lg"
												>
											</div>

											{{-- Details Section --}}
											<div class="w-full md:w-96 border-t border-black/5 md:border-t-0 md:border-l dark:border-white/5 p-6 overflow-y-auto">
												{{-- User Info --}}
												<div class="flex items-center space-x-3 mb-6">
													<div class="w-12 h-12 rounded-full overflow-hidden flex-shrink-0 ring-2 ring-black/5 dark:ring-white/5">
														<template x-if="request.user.avatar">
															<img :src="request.user.avatar" :alt="request.user.name" class="w-full h-full object-cover">
														</template>
														<template x-if="!request.user.avatar">
															<div class="w-full h-full bg-foreground/10 flex items-center justify-center">
																<span class="text-lg font-semibold text-foreground" x-text="request.user.initial"></span>
															</div>
														</template>
													</div>
													<div class="min-w-0 flex-1">
														<p class="font-semibold text-foreground truncate" x-text="request.user.name"></p>
														<p class="text-sm text-foreground/50" x-text="request.created_at"></p>
													</div>
												</div>

												{{-- Status --}}
												<div class="mb-4">
													<h4 class="text-xs font-medium text-foreground/50 uppercase tracking-wide mb-2">{{__('Status')}}</h4>
													<span
														:class="{
															'bg-yellow-500/10 text-yellow-600 border-yellow-500/20': request.status === 'pending',
															'bg-green-500/10 text-green-600 border-green-500/20': request.status === 'approved',
															'bg-red-500/10 text-red-600 border-red-500/20': request.status === 'rejected'
														}"
														class="inline-block px-3 py-1 rounded-lg text-sm font-semibold uppercase border"
														x-text="request.status"
													></span>
												</div>

												{{-- Title/Prompt --}}
												<div class="mb-4">
													<h4 class="text-xs font-medium text-foreground/50 uppercase tracking-wide mb-2">{{__('Prompt')}}</h4>
													<p class="text-foreground font-medium leading-relaxed break-words" x-text="request.title"></p>
												</div>

												{{-- Description --}}
												<div class="mb-4" x-show="request.description">
													<h4 class="text-xs font-medium text-foreground/50 uppercase tracking-wide mb-2">{{__('Description')}}</h4>
													<p class="text-foreground/70 leading-relaxed break-words" x-text="request.description"></p>
												</div>

												{{-- Tags --}}
												<div class="mb-6" x-show="request.tags && request.tags.length > 0">
													<h4 class="text-xs font-medium text-foreground/50 uppercase tracking-wide mb-2">{{__('Tags')}}</h4>
													<div class="flex flex-wrap gap-2">
														<template x-for="tag in request.tags" :key="tag">
															<span class="px-3 py-1 bg-foreground/5 text-foreground/70 rounded-full text-sm break-all" x-text="tag"></span>
														</template>
													</div>
												</div>

												{{-- Actions --}}
												<div class="border-t border-black/5 pt-4 dark:border-white/5 flex flex-col sm:flex-row gap-2" x-show="request.status === 'pending'">
													<x-button
														@click.prevent="approveRequest(request.id); modalOpen = false"
														variant="success"
														class="flex-1"
													>
														<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
															<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
														</svg>
														{{__('Approve')}}
													</x-button>
													<x-button
														@click.prevent="rejectRequest(request.id); modalOpen = false"
														variant="danger"
														class="flex-1"
													>
														<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
															<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
														</svg>
														{{__('Reject')}}
													</x-button>
												</div>

												{{-- Already Reviewed Message --}}
												<div class="pt-4 border-t border-black/5 dark:border-white/5" x-show="request.status !== 'pending'">
													<p class="text-sm text-center text-foreground/70">
														<span x-show="request.status === 'approved'">
															<svg class="w-5 h-5 inline-block text-green-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
																<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
															</svg>
															{{__('Approved by')}} <strong x-text="request.reviewed_by"></strong>
														</span>
														<span x-show="request.status === 'rejected'">
															<svg class="w-5 h-5 inline-block text-red-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
																<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
															</svg>
															{{__('Rejected by')}} <strong x-text="request.reviewed_by"></strong>
														</span>
													</p>
												</div>
											</div>
										</div>
									</x-slot:modal>
								</x-modal>
							</div>

							{{-- Status Badge --}}
							<div class="absolute top-3 right-3">
								<span
									:class="{
										'bg-yellow-500 text-white': request.status === 'pending',
										'bg-green-500 text-white': request.status === 'approved',
										'bg-red-500 text-white': request.status === 'rejected'
									}"
									class="px-3 py-1 rounded-full text-xs font-semibold uppercase shadow-lg"
									x-text="request.status"
								></span>
							</div>
						</div>

						{{-- Content --}}
						<div class="p-5">
							{{-- User Info --}}
							<div class="flex items-center gap-3 mb-3">
								<div class="w-10 h-10 rounded-full bg-foreground/10 flex items-center justify-center overflow-hidden flex-shrink-0 ring-1 ring-foreground/10">
									<template x-if="request.user.avatar">
										<img :src="request.user.avatar" :alt="request.user.name" class="w-full h-full object-cover">
									</template>
									<template x-if="!request.user.avatar">
										<span class="text-sm font-semibold text-foreground" x-text="request.user.initial"></span>
									</template>
								</div>
								<div class="flex-1 min-w-0">
									<p class="font-semibold text-foreground truncate" x-text="request.user.name"></p>
									<p class="text-xs text-foreground/50" x-text="request.created_at"></p>
								</div>
							</div>

							{{-- Title --}}
							<h3 class="font-medium text-foreground mb-2 line-clamp-2" x-text="request.title"></h3>

							{{-- Tags --}}
							<div class="flex flex-wrap gap-1 mb-4" x-show="request.tags && request.tags.length > 0">
								<template x-for="tag in request.tags.slice(0, 3)" :key="tag">
									<span class="px-2 py-1 bg-foreground/5 text-foreground/70 rounded text-xs" x-text="tag"></span>
								</template>
								<span
									x-show="request.tags.length > 3"
									class="px-2 py-1 bg-foreground/5 text-foreground/70 rounded text-xs"
									x-text="'+' + (request.tags.length - 3)"
								></span>
							</div>

							{{-- Actions --}}
							<div class="flex flex-row gap-1 mt-3">
								<x-button
									x-show="request.status === 'pending' || request.status === 'rejected'"
									@click.stop="approveRequest(request.id)"
									variant="secondary"
									class="flex-1 whitespace-nowrap justify-center"
									size="sm"
								>
									{{__('Approve')}}
								</x-button>
								<x-button
									x-show="request.status !== 'rejected'"
									@click.stop="rejectRequest(request.id)"
									variant="danger"
									class="flex-1 whitespace-nowrap justify-center"
									size="sm"
								>
									{{__('Reject')}}
								</x-button>
							</div>

							{{-- Already Processed --}}
							<div x-show="request.status !== 'pending'" class="text-center py-2">
								<p class="text-sm text-foreground/50">
									<span x-show="request.status === 'approved'">{{__('Approved by')}} <span x-text="request.reviewed_by"></span></span>
									<span x-show="request.status === 'rejected'">{{__('Rejected by')}} <span x-text="request.reviewed_by"></span></span>
								</p>
							</div>
						</div>
					</div>
				</x-card>
			</template>
		</div>

		{{-- Empty State --}}
		<div x-show="!loading && requests.length === 0" class="py-20">
			<x-empty-state
				icon="tabler-photo-off"
				title="{{ __('No Requests Found') }}"
				:description="__('No publish requests yet.')"
			>
				<p class="mt-2 text-xs text-foreground/60" x-text="getEmptyMessage()"></p>
			</x-empty-state>
		</div>
	</div>

	<script>
		function publishRequestsManager() {
			return {
				requests: [],
				stats: {
					pending: 0,
					approved: 0,
					rejected: 0,
					total: 0,
					approved_today: 0,
					rejected_today: 0
				},
				filterStatus: 'all',
				loading: false,

				async init() {
					await this.loadRequests();
				},

				async loadRequests() {
					this.loading = true;
					try {
						const response = await fetch(`{{ route('dashboard.admin.ai-image-pro.community-images.publish-requests') }}?status=${this.filterStatus}`);
						const data = await response.json();

						this.requests = data.requests || [];
						this.stats = data.stats || this.stats;
					} catch (error) {
						console.error('Failed to load requests:', error);
						if (typeof toastr !== 'undefined') {
							toastr.error('{{__("Failed to load publish requests. Please try again.")}}');
						}
					} finally {
						this.loading = false;
					}
				},

				async approveRequest(requestId) {
					if (!confirm('{{__("Are you sure you want to approve this request?")}}')) {
						return;
					}

					try {
						const response = await fetch(`/dashboard/admin/ai-image-pro/community-images/publish-requests/${requestId}/approve`, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
							}
						});

						const data = await response.json();

						if (response.ok && data.success) {
							if (typeof toastr !== 'undefined') {
								toastr.success('{{__("Request approved successfully!")}}');
							}
							await this.loadRequests();
						} else {
							throw new Error(data.message || 'Failed to approve request');
						}
					} catch (error) {
						console.error('Failed to approve request:', error);
						if (typeof toastr !== 'undefined') {
							toastr.error('{{__("Failed to approve request. Please try again.")}}');
						}
					}
				},

				async rejectRequest(requestId) {
					if (!confirm('{{__("Are you sure you want to reject this request?")}}')) {
						return;
					}

					try {
						const response = await fetch(`/dashboard/admin/ai-image-pro/community-images/publish-requests/${requestId}/reject`, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
							}
						});

						const data = await response.json();

						if (response.ok && data.success) {
							if (typeof toastr !== 'undefined') {
								toastr.success('{{__("Request rejected successfully!")}}');
							}
							await this.loadRequests();
						} else {
							throw new Error(data.message || 'Failed to reject request');
						}
					} catch (error) {
						console.error('Failed to reject request:', error);
						if (typeof toastr !== 'undefined') {
							toastr.error('{{__("Failed to reject request. Please try again.")}}');
						}
					}
				},

				getEmptyMessage() {
					const messages = {
						all: '{{__("No publish requests yet.")}}',
						pending: '{{__("No pending requests at the moment.")}}',
						approved: '{{__("No approved requests yet.")}}',
						rejected: '{{__("No rejected requests yet.")}}'
					};
					return messages[this.filterStatus] || messages.all;
				}
			};
		}
	</script>
@endsection
