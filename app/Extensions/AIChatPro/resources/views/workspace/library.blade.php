@extends('ai-chat-pro::workspace.shell')
@section('title', __('Library'))
@section('content')
<div class="gpage-head">
    <div><h1>{{ __('Library') }}</h1><p>{{ __('Files you uploaded to Chat Pro and files Garad created for you.') }}</p></div>
    <div class="gactions"><button class="gbtn primary" onclick="garadOpenChatPrompt('I want to upload or create a new file: ')" type="button"><x-tabler-plus class="size-4" /> {{ __('New') }}</button></div>
</div>
<div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;align-items:center">
    <div class="gsearch"><x-tabler-search /><input id="librarySearch" class="ginput" placeholder="{{ __('Search library') }}"></div>
    <div class="gactions"><button class="giconbtn active" data-library-view="grid" title="{{ __('Grid') }}"><x-tabler-layout-grid /></button><button class="giconbtn" data-library-view="list" title="{{ __('List') }}"><x-tabler-list /></button></div>
</div>
<div class="gtabs"><button class="active" data-library-filter="all">{{ __('All') }}</button><button data-library-filter="image">{{ __('Images') }}</button><button data-library-filter="document">{{ __('Documents') }}</button></div>
@php
    $items = collect();
    foreach($documents as $doc){
        $kind = \Illuminate\Support\Str::startsWith((string)$doc->mime_type,'image/') ? 'image' : 'document';
        $items->push(['source'=>'upload','id'=>$doc->id,'kind'=>$kind,'title'=>$doc->original_name,'subtitle'=>$doc->mime_type ?: __('Uploaded file'),'size'=>$doc->file_size,'updated'=>$doc->updated_at,'chat'=>$doc->chat]);
    }
    foreach($artifacts as $a){
        $t = strtolower((string)$a->type);
        $kind = in_array($t,['image','photo','png','jpg','jpeg','webp'],true) ? 'image' : 'document';
        $items->push(['source'=>'created','id'=>$a->id,'kind'=>$kind,'title'=>$a->title ?: $a->original_name,'subtitle'=>__('Created by Garad').' · '.($a->type ?: __('file')),'size'=>$a->file_size,'updated'=>$a->updated_at,'artifact'=>$a]);
    }
    $items=$items->sortByDesc(fn($i)=>optional($i['updated'])->timestamp ?? 0)->values();
@endphp
<div id="libraryGrid" class="gcards">
@forelse($items as $item)
    <article class="gcard library-item" data-kind="{{ $item['kind'] }}" data-search="{{ strtolower(($item['title'] ?? '').' '.($item['subtitle'] ?? '')) }}">
        <div class="gcard-icon">@if($item['kind']==='image')<x-tabler-photo style="width:36px;height:36px" />@else<x-tabler-file-text style="width:36px;height:36px" />@endif</div>
        <h3>{{ $item['title'] ?: __('Untitled file') }}</h3><p>{{ $item['subtitle'] }}</p>
        <p style="margin-top:5px">@if($item['size']){{ number_format($item['size']/1024,1) }} KB · @endif{{ optional($item['updated'])->diffForHumans() }}</p>
        <div class="gcard-actions">
            @if($item['source']==='created')
                <a class="gbtn primary" href="{{ route('dashboard.user.openai.chat.pro.artifacts.download',$item['id']) }}">{{ __('Download') }}</a>
                <button class="gbtn" onclick="garadOpenChatPrompt({{ json_encode('Continue working on my file "'.$item['title'].'": ') }})">{{ __('Continue') }}</button>
            @else
                <button class="gbtn primary" onclick="garadOpenChatPrompt({{ json_encode('Use my uploaded file "'.$item['title'].'" and ') }})">{{ __('Ask Garad') }}</button>
                @if($item['chat'])<a class="gbtn" href="{{ route('dashboard.user.openai.chat.pro.index',['slug'=>$item['chat']->slug ?: $item['chat']->id]) }}">{{ __('Open chat') }}</a>@endif
            @endif
        </div>
    </article>
@empty
    <div class="gempty" style="grid-column:1/-1"><x-tabler-library style="width:30px;height:30px;margin-bottom:10px"/><div>{{ __('Your Chat Pro files will appear here.') }}</div></div>
@endforelse
</div>
@endsection
@push('scripts')
<script>
(function(){
 const grid=document.getElementById('libraryGrid'), search=document.getElementById('librarySearch'); let filter='all';
 function apply(){const q=(search?.value||'').toLowerCase().trim();document.querySelectorAll('.library-item').forEach(i=>{const ok=(filter==='all'||i.dataset.kind===filter)&&(!q||i.dataset.search.includes(q));i.classList.toggle('hide',!ok)});}
 search?.addEventListener('input',apply);
 document.querySelectorAll('[data-library-filter]').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('[data-library-filter]').forEach(x=>x.classList.remove('active'));b.classList.add('active');filter=b.dataset.libraryFilter;apply()}));
 document.querySelectorAll('[data-library-view]').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('[data-library-view]').forEach(x=>x.classList.remove('active'));b.classList.add('active');grid.classList.toggle('glist',b.dataset.libraryView==='list');grid.classList.toggle('gcards',b.dataset.libraryView!=='list');document.querySelectorAll('.library-item').forEach(i=>i.classList.toggle('glist-row',b.dataset.libraryView==='list'))}));
})();
</script>
@endpush
