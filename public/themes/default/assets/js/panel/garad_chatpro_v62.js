(() => {
  'use strict';
  if (window.__GARAD_CHATPRO_V62__) return;
  window.__GARAD_CHATPRO_V62__ = true;

  const tools = [
    {key:'video', tag:'@video', label:'Video'},
    {key:'pdf', tag:'@pdf', label:'PDF'},
    {key:'excel', tag:'@excel', label:'Excel'},
    {key:'word', tag:'@word', label:'Word'},
    {key:'image', tag:'@image', label:'Image'},
    {key:'slides', tag:'@slides', label:'Slides'},
  ];

  const chatBase = () => {
    const p = location.pathname;
    const marker = '/dashboard/user/openai/chat/pro';
    const i = p.indexOf(marker);
    return i >= 0 ? p.slice(0, i) + marker : marker;
  };

  const promptEl = () => document.getElementById('prompt');
  const selectedEl = () => {
    let el = document.getElementById('garad_selected_tool');
    if (!el) {
      const form = document.getElementById('chat_form');
      if (!form) return null;
      el = document.createElement('input');
      el.type = 'hidden';
      el.id = 'garad_selected_tool';
      el.name = 'garad_selected_tool';
      form.appendChild(el);
    }
    return el;
  };

  const setPromptValue = (value, cursor = null) => {
    const el = promptEl();
    if (!el) return;
    el.value = value;
    el.dispatchEvent(new Event('input', {bubbles:true}));
    try { if (el._x_model) el._x_model.set(value); } catch (_) {}
    el.focus();
    const pos = cursor ?? value.length;
    try { el.setSelectionRange(pos, pos); } catch (_) {}
  };

  const chooseTool = (tool) => {
    const el = promptEl();
    if (!el) return;
    const hidden = selectedEl();
    if (hidden) hidden.value = tool.key;
    window.garadActiveRequestTool = tool.key;

    const current = el.value || '';
    const withoutTag = current.replace(/^@(video|pdf|excel|word|image|slides)\b\s*/i, '');
    const next = `${tool.tag} ${withoutTag}`;
    setPromptValue(next, tool.tag.length + 1);
  };

  const installAtPicker = () => {
    const el = promptEl();
    if (!el || el.dataset.garadV62At === '1') return;
    el.dataset.garadV62At = '1';
    const host = el.closest('.relative') || el.parentElement;
    if (!host) return;

    const menu = document.createElement('div');
    menu.id = 'garad-v62-at-menu';
    menu.innerHTML = '<div class="garad-v62-at-title">Create with Garad AI</div><div class="garad-v62-at-list"></div>';
    host.appendChild(menu);
    const list = menu.querySelector('.garad-v62-at-list');
    let visible = [];
    let active = 0;

    const context = () => {
      const value = el.value || '';
      const cursor = el.selectionStart ?? value.length;
      const before = value.slice(0, cursor);
      const at = before.lastIndexOf('@');
      if (at < 0) return null;
      const token = before.slice(at);
      if (/\s/.test(token)) return null;
      return {value,cursor,at,token};
    };

    const close = () => { menu.classList.remove('is-open'); list.innerHTML=''; visible=[]; active=0; };
    const render = (items) => {
      visible = items;
      list.innerHTML = '';
      items.forEach((tool, index) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'garad-v62-at-item' + (index === active ? ' is-active' : '');
        b.innerHTML = `<span>${tool.label}</span><code>${tool.tag}</code>`;
        b.addEventListener('mousedown', e => { e.preventDefault(); insert(tool); });
        list.appendChild(b);
      });
      menu.classList.toggle('is-open', items.length > 0);
    };
    const insert = (tool) => {
      const c = context();
      if (!c) return chooseTool(tool);
      const after = c.value.slice(el.selectionEnd ?? c.cursor);
      const next = c.value.slice(0, c.at) + tool.tag + ' ' + after;
      const pos = (c.value.slice(0, c.at) + tool.tag + ' ').length;
      const hidden = selectedEl(); if (hidden) hidden.value = tool.key;
      window.garadActiveRequestTool = tool.key;
      setPromptValue(next, pos);
      close();
    };
    const update = () => {
      const c = context();
      if (!c) return close();
      const q = c.token.toLowerCase();
      active = 0;
      render(tools.filter(t => t.tag.startsWith(q) || t.label.toLowerCase().startsWith(q.slice(1))));
    };

    el.addEventListener('input', update);
    el.addEventListener('click', update);
    el.addEventListener('blur', () => setTimeout(close, 120));
    el.addEventListener('keydown', e => {
      if (!menu.classList.contains('is-open') || !visible.length) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); active=(active+1)%visible.length; render(visible); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); active=(active-1+visible.length)%visible.length; render(visible); }
      else if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); insert(visible[active]); }
      else if (e.key === 'Escape') close();
    });
  };


  const ensureDynamicCreateButtons = () => {
    const upload = document.querySelector('#chat_form .lqd-chat-attach');
    if (!upload) return;
    const parent = upload.parentElement;
    if (!parent || parent.querySelector('[data-garad-v62-create-wrap]')) return;
    const wrap = document.createElement('div');
    wrap.setAttribute('data-garad-v62-create-wrap','1');
    wrap.style.cssText = 'margin-top:8px;padding-top:8px;border-top:1px solid rgba(127,127,127,.12);display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px;';
    tools.forEach(tool => {
      const button = document.createElement('button');
      button.type = 'button';
      button.setAttribute('data-garad-create-tool', tool.key);
      button.style.cssText = 'display:flex;align-items:center;gap:7px;width:100%;padding:8px 9px;border-radius:9px;font-size:12px;text-align:left;';
      button.innerHTML = `<code style="font-weight:700;color:#5e17ff">${tool.tag}</code><span>${tool.label}</span>`;
      wrap.appendChild(button);
    });
    parent.appendChild(wrap);
  };

  const hookNativeCreateButtons = () => {
    document.querySelectorAll('[data-garad-create-tool]').forEach(btn => {
      if (btn.dataset.garadV62Hook === '1') return;
      btn.dataset.garadV62Hook = '1';
      btn.addEventListener('click', () => {
        const key = (btn.getAttribute('data-garad-create-tool') || '').toLowerCase();
        const tool = tools.find(t => t.key === key || (key === 'presentation' && t.key === 'slides'));
        if (tool) setTimeout(() => chooseTool(tool), 0);
      });
    });
  };

  const syncSelectedToolFromPrompt = () => {
    const el = promptEl(); if (!el) return;
    const m = (el.value || '').trim().match(/^@(video|pdf|excel|word|image|slides)\b/i);
    const hidden = selectedEl();
    if (!hidden) return;
    if (m) { hidden.value = m[1].toLowerCase(); window.garadActiveRequestTool = hidden.value; }
  };

  const autosize = () => {
    const el = promptEl(); if (!el || el.dataset.garadV62Auto === '1') return;
    el.dataset.garadV62Auto = '1';
    const run=()=>{el.style.height='auto';el.style.height=Math.min(Math.max(el.scrollHeight,58),210)+'px';};
    el.addEventListener('input', run, {passive:true}); run();
  };

  const markdownLink = text => {
    const m=(text||'').match(/\[([^\]]+)\]\(([^)]+)\)/); return m ? {label:m[1],url:m[2]} : null;
  };
  const abs = u => /^https?:\/\//i.test(u) ? u : location.origin + (u.startsWith('/')?'':'/') + u;

  const jobCard = (bubble,type,id) => {
    const cls=`garad-v62-${type}-${CSS.escape(String(id))}`;
    let card=bubble.parentElement?.querySelector('.'+cls);
    if (card) return card;
    card=document.createElement('div'); card.className=`garad-v62-job-card ${cls}`;
    card.innerHTML=`<div class="garad-v62-job-head"><span class="garad-v62-spinner"></span><span>${type==='video'?'Generating video…':'Generating slides…'}</span></div><div class="garad-v62-job-status">Processing…</div><div class="garad-v62-job-media"></div>`;
    bubble.insertAdjacentElement('afterend',card); return card;
  };

  const renderJob = (card,type,message) => {
    const status=card.querySelector('.garad-v62-job-status'); if(status) status.textContent=(message||'Processing…').replace(/[`*]/g,'').trim();
    const link=markdownLink(message||'');
    if(!link) return false;
    card.classList.add('is-done');
    const media=card.querySelector('.garad-v62-job-media'); media.innerHTML='';
    const url=abs(link.url);
    if(type==='video') { const v=document.createElement('video'); v.controls=true; v.preload='metadata'; v.src=url; media.appendChild(v); }
    const a=document.createElement('a'); a.href=url; a.target='_blank'; a.rel='noopener'; a.textContent=link.label||'Open result'; media.appendChild(a);
    return true;
  };

  const poll = (bubble,type,id) => {
    const key=`__garad_v62_poll_${type}_${id}`; if(window[key]) return;
    const card=jobCard(bubble,type,id);
    const encoded=encodeURIComponent(id);
    const url=type==='video' ? `${chatBase()}/garad-video-status/${encoded}` : `${chatBase()}/garad-presentation-status/${encoded}`;
    let attempts=0;
    const tick=async()=>{
      attempts++;
      try{
        const r=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
        const data=await r.json();
        const msg=data?.message||'Processing…';
        if(renderJob(card,type,msg)){clearInterval(window[key]);window[key]=null;return;}
        const status=card.querySelector('.garad-v62-job-status'); if(status) status.textContent=msg.replace(/[`*]/g,'').trim();
        if(!data?.ok && /failed|error|not found|credits/i.test(msg)){clearInterval(window[key]);window[key]=null;card.classList.add('is-done');}
      }catch(_){/* keep polling transient network errors */}
      if(attempts>=100){clearInterval(window[key]);window[key]=null;}
    };
    tick(); window[key]=setInterval(tick,10000);
  };

  const scanJobs = () => {
    document.querySelectorAll('.lqd-chat-ai-bubble').forEach(bubble=>{
      const text=bubble.textContent||'';
      const vm=text.match(/Video ID:\s*`?(\d+)`?/i);
      if(vm && /video generation started/i.test(text)) poll(bubble,'video',vm[1]);
      const pm=text.match(/Generation ID:\s*`?([^\s`]+)`?/i);
      if(pm && /presentation generation started/i.test(text)) poll(bubble,'presentation',pm[1]);
    });
  };

  const boot=()=>{
    ensureDynamicCreateButtons(); installAtPicker(); hookNativeCreateButtons(); autosize(); scanJobs();
    const el=promptEl(); if(el && el.dataset.garadV62Sync!=='1'){el.dataset.garadV62Sync='1';el.addEventListener('input',syncSelectedToolFromPrompt);}
    const observer=new MutationObserver(()=>{ensureDynamicCreateButtons();installAtPicker();hookNativeCreateButtons();autosize();scanJobs();});
    observer.observe(document.body,{childList:true,subtree:true});
  };

  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',boot,{once:true}); else boot();
})();
