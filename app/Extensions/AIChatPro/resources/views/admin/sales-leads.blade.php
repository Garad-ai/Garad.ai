@extends('panel.layout.app')
@section('title', __('Sales Leads'))
@section('titlebar_subtitle', __('Enterprise inquiries from the Contact sales page. Each one is also emailed to the sales inbox.'))

@section('content')
    <div class="py-6">
        <div class="container-xl">
            <div class="mb-4 flex flex-wrap gap-2">
                @php
                    $tabs = ['' => __('All'), 'new' => __('New'), 'contacted' => __('Contacted'), 'closed' => __('Closed')];
                @endphp
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('dashboard.admin.openai.chat.pro.sales-leads.index', $key !== '' ? ['status' => $key] : []) }}"
                       class="btn btn-sm {{ $status === (string) $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                        {{ $label }}
                        @if ($key !== '')
                            ({{ $counts[$key] ?? 0 }})
                        @endif
                    </a>
                @endforeach
            </div>

            @if (session('success'))
                <div class="alert alert-success mb-4">{{ session('success') }}</div>
            @endif

            <div class="card">
                <div class="card-table table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th>{{ __('Company') }}</th>
                                <th>{{ __('Contact') }}</th>
                                <th>{{ __('Interest') }}</th>
                                <th>{{ __('Needs') }}</th>
                                <th>{{ __('Status') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="align-middle text-heading">
                            @forelse ($leads as $lead)
                                <tr>
                                    <td class="whitespace-nowrap">
                                        {{ \Illuminate\Support\Carbon::parse($lead->created_at)->format('j M Y H:i') }}
                                    </td>
                                    <td>
                                        <strong>{{ $lead->company_name }}</strong><br>
                                        <span class="text-muted">{{ $lead->company_size }}</span>
                                    </td>
                                    <td>
                                        {{ $lead->first_name }} {{ $lead->last_name }}<br>
                                        <a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a><br>
                                        <a href="tel:{{ $lead->phone }}">{{ $lead->phone }}</a>
                                    </td>
                                    <td>{{ $lead->interest }}</td>
                                    <td style="max-width: 320px; white-space: pre-wrap;">{{ $lead->message ?: '-' }}</td>
                                    <td>
                                        <form method="POST"
                                              action="{{ route('dashboard.admin.openai.chat.pro.sales-leads.update', $lead->id) }}">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" class="form-control min-w-[110px]" onchange="this.form.submit()">
                                                @foreach (['new' => __('New'), 'contacted' => __('Contacted'), 'closed' => __('Closed')] as $value => $label)
                                                    <option value="{{ $value }}" @selected($lead->status === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <form method="POST"
                                              action="{{ route('dashboard.admin.openai.chat.pro.sales-leads.destroy', $lead->id) }}"
                                              onsubmit="return confirm('{{ __('Delete this lead?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">{{ __('Delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-8">{{ __('No inquiries yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $leads->links() }}</div>
        </div>
    </div>
@endsection
