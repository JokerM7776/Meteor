'use strict';
// Big translation tables: send only the cells that changed, so the form stays far below
// PHP's max_input_vars. Without JavaScript every cell is sent and the server still accepts it.
(() => {
  for (const form of document.querySelectorAll('form[data-changed-only]')) {
    form.addEventListener('submit', () => {
      for (const field of form.querySelectorAll('input[name^="strings["], textarea[name^="strings["]')) {
        if (field.value === field.defaultValue && !field.hasAttribute('data-unsaved')) field.disabled = true;
      }
    });
    // Re-enable after a back/forward cache restore.
    window.addEventListener('pageshow', () => { for (const field of form.querySelectorAll('[disabled]')) field.disabled = false; });
  }
})();
(() => {
  const login = document.getElementById('passkey-login');
  const add = document.getElementById('passkey-add');
  if (!login && !add) return;
  const message = document.getElementById('passkey-message');
  const decode = text => Uint8Array.from(atob(text.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - text.length % 4) % 4)), c => c.charCodeAt(0));
  const encode = bytes => btoa(String.fromCharCode(...new Uint8Array(bytes))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  const post = async (path, data = {}) => {
    const response = await fetch(path, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content}, body: JSON.stringify(data)});
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Please reload and try again.');
    return result;
  };
  const options = json => {
    json.challenge = decode(json.challenge);
    if (json.user) json.user.id = decode(json.user.id);
    for (const list of ['excludeCredentials', 'allowCredentials']) {
      if (json[list]) json[list] = json[list].map(item => ({...item, id: decode(item.id)}));
    }
    return json;
  };
  const serialize = credential => {
    const response = {clientDataJSON: encode(credential.response.clientDataJSON)};
    for (const key of ['attestationObject', 'authenticatorData', 'signature', 'userHandle']) {
      if (credential.response[key]) response[key] = encode(credential.response[key]);
    }
    if (credential.response.getTransports) response.transports = credential.response.getTransports();
    return {id: credential.id, type: credential.type, response};
  };
  if (!window.PublicKeyCredential || !navigator.credentials) {
    message.textContent = 'Passkeys are not supported here. Use your password to sign in.';
    if (login) login.disabled = true;
    if (add) add.querySelector('button').disabled = true;
    return;
  }
  let pending;
  let operation = Promise.resolve();
  const signIn = async conditional => {
    const previous = operation;
    if (pending) pending.abort();
    let finish;
    operation = new Promise(resolve => { finish = resolve; });
    await previous;
    pending = new AbortController();
    try {
      if (!conditional) { login.disabled = true; message.textContent = 'Waiting for your passkey…'; }
      const publicKey = options(await post('/admin/passkey/login/options'));
      const credential = await navigator.credentials.get({publicKey, signal: pending.signal, ...(conditional ? {mediation: 'conditional'} : {})});
      const result = await post('/admin/passkey/login/verify', serialize(credential));
      window.location.assign(result.redirect);
    } catch (error) {
      if (error.name !== 'AbortError' && !conditional) message.textContent = error.name === 'NotAllowedError' ? 'Passkey sign-in was cancelled or timed out. Try again or use your password.' : error.message;
    } finally { if (login) login.disabled = false; finish(); }
  };
  if (login) {
    login.addEventListener('click', () => signIn(false));
    if (PublicKeyCredential.isConditionalMediationAvailable) {
      PublicKeyCredential.isConditionalMediationAvailable().then(available => { if (available) signIn(true); }).catch(() => {});
    }
  }
  if (add) add.addEventListener('submit', async event => {
    event.preventDefault();
    const button = add.querySelector('button'); button.disabled = true;
    try {
      message.textContent = 'Follow your device’s instructions to create a passkey.';
      const publicKey = options(await post('/admin/security/passkeys/options'));
      const credential = await navigator.credentials.create({publicKey});
      const result = await post('/admin/security/passkeys', {...serialize(credential), name: document.getElementById('passkey-name').value});
      window.location.assign(result.redirect);
    } catch (error) { message.textContent = error.name === 'NotAllowedError' ? 'Passkey creation was cancelled or timed out. You can try again.' : error.message; }
    finally { button.disabled = false; }
  });
})();

// Dialogs: close buttons, and a click on the backdrop closes too.
for (const dialog of document.querySelectorAll('dialog.admin-dialog')) {
  dialog.addEventListener('click', event => { if (event.target === dialog || event.target.closest('[data-close]')) dialog.close(); });
}

// Media picker: one dialog that lists the library and uploads, used by the editor's Image
// button and by image fields (cover, site icon). open() takes a title, the button text,
// whether to ask for alt text, and onPick(item, alt).
const mediaPicker = (() => {
  const picker = document.getElementById('media-picker');
  if (!picker) return null;
  const heading = picker.querySelector('#picker-heading');
  const grid = picker.querySelector('[data-picker-grid]');
  const status = picker.querySelector('[data-picker-status]');
  const search = picker.querySelector('[data-picker-search]');
  const categorySelect = search.elements.category;
  const pageLabel = picker.querySelector('[data-picker-page]');
  const prev = picker.querySelector('[data-picker-prev]');
  const next = picker.querySelector('[data-picker-next]');
  const altField = picker.querySelector('[data-picked-alt-field]');
  const altInput = picker.querySelector('[data-picked-alt]');
  const thumb = picker.querySelector('[data-picked-thumb]');
  const chooseButton = picker.querySelector('[data-picker-insert]');
  const upload = picker.querySelector('[data-picker-upload]');
  const uploadStatus = picker.querySelector('[data-upload-status]');
  const tabs = [...picker.querySelectorAll('[role="tab"]')];
  let page = 1, pages = 1, picked = null, request = 0, onPick = null;
  const showTab = tab => {
    for (const other of tabs) {
      const selected = other === tab;
      other.setAttribute('aria-selected', String(selected));
      other.tabIndex = selected ? 0 : -1;
      document.getElementById(other.getAttribute('aria-controls')).hidden = !selected;
    }
  };
  for (const tab of tabs) {
    tab.addEventListener('click', () => showTab(tab));
    tab.addEventListener('keydown', event => {
      if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
      const other = tabs[(tabs.indexOf(tab) + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
      showTab(other); other.focus();
    });
  }
  const pick = item => {
    picked = item;
    for (const button of grid.querySelectorAll('button')) button.setAttribute('aria-pressed', String(Number(button.dataset.id) === item.id));
    altInput.value = item.alt;
    thumb.src = item.thumb; thumb.classList.remove('hidden');
    chooseButton.disabled = false;
  };
  const choose = () => {
    if (!picked) return;
    const item = picked, alt = altInput.value.trim();
    picker.close();
    if (onPick) onPick(item, alt);
  };
  const render = (items, preselect) => {
    grid.replaceChildren();
    for (const item of items) {
      const li = document.createElement('li');
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'picker-item'; button.dataset.id = item.id;
      button.setAttribute('aria-pressed', String(Boolean(picked && picked.id === item.id)));
      const img = document.createElement('img');
      img.src = item.thumb; img.alt = ''; img.loading = 'lazy';
      const name = document.createElement('span');
      name.className = 'picker-name'; name.textContent = item.name;
      button.title = `${item.name} · ${item.width}×${item.height}`;
      button.append(img, name);
      button.addEventListener('click', () => pick(item));
      button.addEventListener('dblclick', () => { pick(item); choose(); });
      li.append(button);
      grid.append(li);
    }
    if (preselect) pick(preselect);
  };
  const load = async (preselect = null) => {
    const id = ++request;
    status.textContent = 'Loading…';
    const params = new URLSearchParams({q: search.elements.q.value, category: categorySelect.value, page: String(page)});
    try {
      const response = await fetch('/admin/media/library?' + params, {credentials: 'same-origin', headers: {Accept: 'application/json'}});
      if (!response.ok) throw new Error();
      const data = await response.json();
      if (id !== request) return;
      page = data.page; pages = data.pages;
      const chosen = categorySelect.value;
      categorySelect.replaceChildren(new Option('All categories', ''), ...data.categories.map(name => new Option(name, name, false, name === chosen)));
      picker.querySelector('#picker-categories').replaceChildren(...data.categories.map(name => new Option(name)));
      render(data.items, preselect);
      status.textContent = data.total ? `${data.total} ${data.total === 1 ? 'image' : 'images'}. Choose one, then ${chooseButton.textContent}.` : (params.get('q') || params.get('category') ? 'No images match this search.' : 'No images yet. Upload one in the Upload tab.');
      pageLabel.textContent = `Page ${page} of ${pages}`;
      prev.disabled = page <= 1; next.disabled = page >= pages;
    } catch (error) { if (id === request) status.textContent = 'Images could not be loaded. Check your connection and try again.'; }
  };
  const open = ({title = 'Insert image', action = 'Insert image', withAlt = true, onPick: callback = null} = {}) => {
    onPick = callback;
    heading.textContent = title;
    chooseButton.textContent = action;
    altField.hidden = !withAlt;
    picked = null; chooseButton.disabled = true; altInput.value = ''; thumb.classList.add('hidden'); uploadStatus.textContent = '';
    showTab(tabs[0]);
    picker.showModal();
    search.elements.q.focus();
    load();
  };
  chooseButton.addEventListener('click', choose);
  search.addEventListener('submit', event => { event.preventDefault(); page = 1; load(); });
  categorySelect.addEventListener('change', () => { page = 1; load(); });
  prev.addEventListener('click', () => { if (page > 1) { page--; load(); } });
  next.addEventListener('click', () => { if (page < pages) { page++; load(); } });
  upload.addEventListener('submit', async event => {
    event.preventDefault();
    const button = upload.querySelector('button');
    button.disabled = true; uploadStatus.textContent = 'Uploading…';
    try {
      const response = await fetch('/admin/media/upload', {method: 'POST', credentials: 'same-origin', headers: {Accept: 'application/json'}, body: new FormData(upload)});
      const data = await response.json().catch(() => ({error: 'The upload failed. The file may be larger than this server accepts.'}));
      if (!response.ok || !data.item) throw new Error(data.error || 'The upload failed. Try again.');
      upload.reset();
      uploadStatus.textContent = data.note || '';
      search.elements.q.value = ''; categorySelect.value = ''; page = 1;
      showTab(tabs[0]);
      await load(data.item);
      if (data.note) status.textContent = data.note;
      chooseButton.focus();
    } catch (error) { uploadStatus.textContent = error.message; }
    finally { button.disabled = false; }
  });
  return {open};
})();

// Image fields (post cover, site icon): a hidden URL chosen with the media picker.
for (const field of document.querySelectorAll('[data-media-field]')) {
  const value = field.querySelector('[data-media-value]');
  const preview = field.querySelector('[data-media-preview]');
  const empty = field.querySelector('[data-media-empty]');
  const choose = field.querySelector('[data-media-choose]');
  const clear = field.querySelector('[data-media-clear]');
  const show = (url, thumb) => {
    value.value = url;
    if (url) preview.src = thumb || url;
    preview.hidden = !url; empty.hidden = Boolean(url); clear.hidden = !url;
    value.dispatchEvent(new Event('input', {bubbles: true}));
  };
  if (!mediaPicker) { choose.disabled = true; continue; }
  choose.addEventListener('click', () => mediaPicker.open({
    title: field.dataset.pickerTitle, action: field.dataset.pickerAction, withAlt: false,
    onPick: item => { show(item.url, item.thumb); choose.focus(); },
  }));
  clear.addEventListener('click', () => { show('', ''); choose.focus(); });
}

// Content editor: a Visual mode and a Markdown mode over the same textarea, a formatting
// toolbar for both, and the media picker. Without JavaScript the plain textarea remains.
(() => {
  const root = document.querySelector('[data-md-editor]');
  if (!root) return;
  const textarea = root.querySelector('textarea');
  const visual = root.querySelector('[data-visual]');
  const toolbar = root.querySelector('[data-toolbar]');
  const switcher = root.querySelector('[data-mode-switch]');
  let mode = 'markdown';
  let dirty = false;

  // Markdown -> HTML for the Visual mode. Mirrors the subset of core/Markdown.php.
  const esc = text => text.replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
  const safeUrl = (url, image) => {
    if (!url || /[\x00-\x20\x7f\\]/.test(url) || url.startsWith('//')) return false;
    const scheme = /^([a-z][a-z0-9+.-]*):/i.exec(url);
    return !scheme || (image ? ['http', 'https'] : ['http', 'https', 'mailto']).includes(scheme[1].toLowerCase());
  };
  const inline = text => {
    const pattern = /`([^`\n]+)`|(!?)\[([^\]\n]*)\]\(([^\s()]+)(?:\s+"([^"\n]*)")?\)|\*\*(.+?)\*\*|__(.+?)__|\*([^*\n]+)\*|(?<!\w)_([^_\n]+)_(?!\w)/gu;
    let html = '', last = 0, m;
    while ((m = pattern.exec(text))) {
      html += esc(text.slice(last, m.index));
      if (m[1] !== undefined) html += '<code>' + esc(m[1]) + '</code>';
      else if (m[3] !== undefined) {
        const image = m[2] === '!';
        const title = m[5] !== undefined ? ` title="${esc(m[5])}"` : '';
        if (!safeUrl(m[4], image)) html += esc(m[0]);
        else html += image ? `<img src="${esc(m[4])}" alt="${esc(m[3])}"${title}>` : `<a href="${esc(m[4])}"${title}>${esc(m[3])}</a>`;
      } else if (m[6] !== undefined || m[7] !== undefined) html += '<strong>' + inline(m[6] ?? m[7]) + '</strong>';
      else html += '<em>' + inline(m[8] ?? m[9]) + '</em>';
      last = pattern.lastIndex;
    }
    return html + esc(text.slice(last));
  };
  const listItem = line => {
    const m = /^( *)([-+*]|\d+\.)\s+(.+)$/.exec(line.replace(/\t/g, '    '));
    return m ? [m[1].length, /\d/.test(m[2][0]) ? 'ol' : 'ul', m[3]] : null;
  };
  const startsBlock = line => listItem(line) !== null || /^\s*(?:#{1,6}\s|>|`{3,}|~{3,}|(?:\*\s*){3,}$|(?:-\s*){3,}$|(?:_\s*){3,}$)/.test(line);
  const listing = (lines, i, indent, depth) => {
    const type = listItem(lines[i])[1];
    let html = `<${type}>`, item, child;
    while (i < lines.length && (item = listItem(lines[i])) && item[0] === indent && item[1] === type) {
      html += '<li>' + inline(item[2]); i++;
      while (i < lines.length && (child = listItem(lines[i])) && child[0] > indent) {
        if (depth === 0) { const [nested, next] = listing(lines, i, child[0], 1); html += nested; i = next; }
        else { html += ' ' + inline(child[2]); i++; }
      }
      html += '</li>';
    }
    return [html + `</${type}>`, i];
  };
  const blocks = lines => {
    let html = '';
    for (let i = 0; i < lines.length;) {
      const line = lines[i++];
      let m;
      if (line.trim() === '') continue;
      if ((m = /^\s*(`{3,}|~{3,})([a-zA-Z0-9_-]*)\s*$/.exec(line))) {
        const closing = new RegExp('^\\s*' + (m[1][0] === '`' ? '`' : '~') + '{' + m[1].length + ',}\\s*$');
        const code = [];
        while (i < lines.length && !closing.test(lines[i])) code.push(lines[i++]);
        if (i < lines.length) i++;
        html += `<pre data-lang="${esc(m[2].toLowerCase())}"><code>${esc(code.join('\n'))}</code></pre>`;
      } else if ((m = /^ {0,3}(#{1,6})\s+(.+?)\s*#*\s*$/u.exec(line))) {
        html += `<h${m[1].length}>${inline(m[2])}</h${m[1].length}>`;
      } else if (/^ {0,3}(?:(?:\*\s*){3,}|(?:-\s*){3,}|(?:_\s*){3,})$/.test(line)) {
        html += '<hr>';
      } else if ((m = /^\s*>\s?(.*)$/.exec(line))) {
        const quote = [m[1]];
        while (i < lines.length && (m = /^\s*>\s?(.*)$/.exec(lines[i]))) { quote.push(m[1]); i++; }
        html += '<blockquote>' + (blocks(quote) || '<p><br></p>') + '</blockquote>';
      } else if (listItem(line)) {
        const [list, next] = listing(lines, i - 1, listItem(line)[0], 0);
        html += list; i = next;
      } else {
        const paragraph = [line];
        while (i < lines.length && lines[i].trim() !== '' && !startsBlock(lines[i])) paragraph.push(lines[i++]);
        html += '<p>' + inline(paragraph.join('\n')).replace(/\n/g, '<br>') + '</p>';
      }
    }
    return html;
  };
  const toHtml = markdown => blocks(markdown.replace(/\r\n?/g, '\n').split('\n')) || '<p><br></p>';

  // HTML of the Visual mode -> Markdown.
  const BLOCK = /^(P|DIV|H[1-6]|BLOCKQUOTE|UL|OL|PRE|HR|FIGURE|TABLE|SECTION|ARTICLE)$/;
  const LIST = /^(UL|OL)$/;
  const clean = text => text.split('\n').map(line => line.replace(/[ \t]+/g, ' ').trim()).join('\n').replace(/\n{3,}/g, '\n\n').trim();
  const wrap = (text, mark) => {
    const m = /^(\s*)([\s\S]*?)(\s*)$/.exec(text);
    return m[2] ? m[1] + mark + m[2] + mark + m[3] : text;
  };
  const label = text => text.replace(/[\[\]\n]/g, ' ').replace(/\s+/g, ' ').trim();
  const href = url => url.trim().replace(/[\s()]/g, c => encodeURIComponent(c));
  const inlineOf = nodes => {
    let out = '';
    for (const node of nodes) {
      if (node.nodeType === Node.TEXT_NODE) { out += node.nodeValue.replace(/ /g, ' ').replace(/[ \t\r\n]+/g, ' '); continue; }
      if (node.nodeType !== Node.ELEMENT_NODE) continue;
      const tag = node.tagName;
      const title = node.getAttribute('title') ? ` "${node.getAttribute('title').replace(/"/g, '')}"` : '';
      if (tag === 'BR') out += '\n';
      else if (tag === 'STRONG' || tag === 'B') out += wrap(inlineOf(node.childNodes), '**');
      else if (tag === 'EM' || tag === 'I') out += wrap(inlineOf(node.childNodes), '*');
      else if (tag === 'CODE') { const code = node.textContent.replace(/[`\n]/g, ''); if (code) out += '`' + code + '`'; }
      else if (tag === 'A') { const text = label(inlineOf(node.childNodes)); const url = node.getAttribute('href') || ''; out += url ? `[${text || url}](${href(url)}${title})` : text; }
      else if (tag === 'IMG') { const src = node.getAttribute('src') || ''; if (src) out += `![${label(node.getAttribute('alt') || '')}](${href(src)}${title})`; }
      else if (BLOCK.test(tag) || tag === 'LI') out += '\n' + inlineOf(node.childNodes) + '\n';
      else out += inlineOf(node.childNodes);
    }
    return out;
  };
  const list = (node, depth) => {
    const lines = [];
    let number = 1;
    for (const item of node.children) {
      if (LIST.test(item.tagName)) { lines.push(list(item, depth + 1)); continue; }
      const nested = [], parts = [];
      for (const child of item.childNodes) {
        if (child.nodeType === Node.ELEMENT_NODE && LIST.test(child.tagName)) nested.push(list(child, depth + 1));
        else parts.push(child);
      }
      const text = clean(inlineOf(parts)).replace(/\n+/g, ' ');
      if (text) lines.push('   '.repeat(depth) + (node.tagName === 'OL' ? `${number++}. ` : '- ') + text);
      lines.push(...nested.filter(Boolean));
    }
    return lines.join('\n');
  };
  const block = node => {
    const tag = node.tagName;
    if (/^H[1-6]$/.test(tag)) { const text = clean(inlineOf(node.childNodes)).replace(/\n+/g, ' '); return text ? '#'.repeat(+tag[1]) + ' ' + text : ''; }
    if (tag === 'HR') return '---';
    if (tag === 'PRE') {
      const code = (visual.hidden ? node.textContent : node.innerText).replace(/ /g, ' ').replace(/\n$/, '');
      let fence = '```';
      while (code.includes(fence)) fence += '`';
      return fence + (node.dataset.lang || '') + '\n' + code + '\n' + fence;
    }
    if (tag === 'BLOCKQUOTE') { const inner = toMarkdown(node); return inner ? inner.split('\n').map(line => line ? '> ' + line : '>').join('\n') : ''; }
    if (LIST.test(tag)) return list(node, 0);
    if ([...node.children].some(child => BLOCK.test(child.tagName))) return toMarkdown(node);
    return clean(inlineOf(node.childNodes));
  };
  const toMarkdown = container => {
    const out = [];
    let loose = [];
    const flush = () => { const text = clean(inlineOf(loose)); if (text) out.push(text); loose = []; };
    for (const node of container.childNodes) {
      if (node.nodeType === Node.ELEMENT_NODE && BLOCK.test(node.tagName)) { flush(); const text = block(node); if (text) out.push(text); }
      else loose.push(node);
    }
    flush();
    return out.join('\n\n');
  };

  // Modes.
  const sync = () => { if (mode === 'visual' && dirty) { textarea.value = toMarkdown(visual); dirty = false; } };
  const setMode = next => {
    if (next === 'visual' && mode !== 'visual') { visual.innerHTML = toHtml(textarea.value); dirty = false; }
    if (next === 'markdown') sync();
    mode = next;
    visual.hidden = mode !== 'visual';
    textarea.hidden = mode === 'visual';
    for (const button of switcher.querySelectorAll('[data-mode]')) button.setAttribute('aria-pressed', String(button.dataset.mode === mode));
    try { localStorage.setItem('meteor.editorMode', mode); } catch (error) { /* private window */ }
    refresh();
  };
  switcher.hidden = false;
  toolbar.hidden = false;
  textarea.classList.add('md-joined');
  switcher.addEventListener('click', event => { const button = event.target.closest('[data-mode]'); if (button) { setMode(button.dataset.mode); (mode === 'visual' ? visual : textarea).focus(); } });
  textarea.form.addEventListener('submit', sync);
  visual.addEventListener('input', () => { dirty = true; });
  try { document.execCommand('defaultParagraphSeparator', false, 'p'); document.execCommand('styleWithCSS', false, false); } catch (error) { /* older browsers */ }
  // A click in the empty space below the text puts the caret at the end, on a new line
  // when the last block cannot take text (an image, a divider or a code block).
  const caretAtEnd = () => {
    let last = visual.lastElementChild;
    if (!last || !/^(P|H[1-6]|BLOCKQUOTE|UL|OL)$/.test(last.tagName) || (last.tagName === 'P' && last.querySelector('img') && !last.textContent.trim())) {
      last = document.createElement('p'); last.append(document.createElement('br')); visual.append(last);
    }
    // Inside the last line of the block, before a trailing <br> (Chrome cannot type after it).
    let target = last;
    while (target.lastElementChild && /^(UL|OL|LI|BLOCKQUOTE|P)$/.test(target.lastElementChild.tagName)) target = target.lastElementChild;
    const range = document.createRange();
    if (target.lastChild && target.lastChild.nodeName === 'BR') range.setStartBefore(target.lastChild); else { range.selectNodeContents(target); range.collapse(false); }
    range.collapse(true);
    const selection = getSelection(); selection.removeAllRanges(); selection.addRange(range);
  };
  visual.addEventListener('mouseup', event => {
    if (event.target !== visual) return;
    const last = visual.lastElementChild;
    if (!last || event.clientY > last.getBoundingClientRect().bottom || !visual.contains(getSelection().anchorNode) || getSelection().anchorNode === visual) caretAtEnd();
  });
  // Pasted or dropped content arrives as plain text, so no foreign HTML reaches the post.
  visual.addEventListener('paste', event => {
    event.preventDefault();
    document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
  });
  visual.addEventListener('drop', event => {
    event.preventDefault();
    const text = event.dataTransfer.getData('text/plain');
    if (text) document.execCommand('insertText', false, text);
  });

  // Selection helpers, so dialogs can insert at the place the writer left.
  let savedRange = null, savedText = [0, 0];
  const remember = () => {
    if (mode === 'markdown') { savedText = [textarea.selectionStart, textarea.selectionEnd]; return; }
    const selection = getSelection();
    savedRange = selection.rangeCount && visual.contains(selection.getRangeAt(0).commonAncestorContainer) ? selection.getRangeAt(0).cloneRange() : null;
  };
  const restore = () => {
    if (mode === 'markdown') { textarea.focus(); textarea.setSelectionRange(...savedText); return; }
    visual.focus();
    const selection = getSelection();
    selection.removeAllRanges();
    if (savedRange) selection.addRange(savedRange);
    else caretAtEnd();
  };
  const selectedText = () => mode === 'markdown' ? textarea.value.slice(textarea.selectionStart, textarea.selectionEnd) : getSelection().toString();
  const insertHtml = html => { document.execCommand('insertHTML', false, html); dirty = true; };

  // Markdown mode: edit the text around the selection.
  const replace = (start, end, text, selectStart = start + text.length, selectEnd = selectStart) => {
    textarea.focus();
    textarea.setSelectionRange(start, end);
    if (!document.execCommand('insertText', false, text)) { textarea.setRangeText(text, start, end, 'end'); textarea.dispatchEvent(new Event('input', {bubbles: true})); }
    textarea.setSelectionRange(selectStart, selectEnd);
  };
  const surround = (before, after, placeholder) => {
    const start = textarea.selectionStart, end = textarea.selectionEnd, value = textarea.value;
    if (value.slice(start - before.length, start) === before && value.slice(end, end + after.length) === after) {
      replace(start - before.length, end + after.length, value.slice(start, end), start - before.length, end - before.length); return;
    }
    const text = value.slice(start, end) || placeholder;
    replace(start, end, before + text + after, start + before.length, start + before.length + text.length);
  };
  const lines = transform => {
    const value = textarea.value;
    const start = value.lastIndexOf('\n', textarea.selectionStart - 1) + 1;
    let end = value.indexOf('\n', Math.max(textarea.selectionEnd - (textarea.selectionEnd > textarea.selectionStart && value[textarea.selectionEnd - 1] === '\n' ? 1 : 0), start));
    if (end < 0) end = value.length;
    const text = transform(value.slice(start, end).split('\n')).join('\n');
    // Keep a selection the writer made; otherwise leave the caret at the end of the line.
    if (textarea.selectionStart === textarea.selectionEnd) replace(start, end, text);
    else replace(start, end, text, start, start + text.length);
  };
  const PREFIX = /^(#{1,6}\s+|>\s?|\s*(?:[-+*]|\d+\.)\s+)/;
  const prefix = (marker, numbered) => lines(rows => {
    const all = rows.filter(row => row.trim()).every(row => numbered ? /^\d+\.\s/.test(row) : row.startsWith(marker));
    let number = 1;
    return rows.map(row => {
      if (!row.trim()) return row;
      const bare = row.replace(PREFIX, '');
      return all ? bare : (numbered ? `${number++}. ` : marker) + bare;
    });
  });
  const fence = (before, after) => {
    const start = textarea.selectionStart, end = textarea.selectionEnd, value = textarea.value;
    const lead = start === 0 || value.slice(0, start).endsWith('\n\n') ? '' : (value.slice(0, start).endsWith('\n') ? '\n' : '\n\n');
    const text = lead + before + value.slice(start, end) + after + (value.slice(end).startsWith('\n') ? '' : '\n');
    replace(start, end, text, start + lead.length + before.length, start + lead.length + before.length + (end - start));
  };

  // Visual mode: browser editing commands.
  const formatBlock = tag => {
    const current = (document.queryCommandValue('formatBlock') || '').toLowerCase();
    document.execCommand('formatBlock', false, current === tag ? '<p>' : `<${tag}>`);
  };
  const toggleCode = () => {
    const node = getSelection().anchorNode;
    const code = node && (node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement).closest('code');
    if (code && visual.contains(code) && !code.closest('pre')) { code.replaceWith(document.createTextNode(code.textContent)); dirty = true; return; }
    insertHtml('<code>' + esc(selectedText() || 'code') + '</code>');
  };
  const markdownImage = (url, alt) => `![${label(alt)}](${href(url)})`;
  const commands = {
    p: () => mode === 'visual' ? formatBlock('p') : lines(rows => rows.map(row => row.replace(PREFIX, ''))),
    h2: () => mode === 'visual' ? formatBlock('h2') : lines(rows => rows.map(row => /^##\s/.test(row) && !/^###/.test(row) ? row.replace(PREFIX, '') : '## ' + row.replace(PREFIX, ''))),
    h3: () => mode === 'visual' ? formatBlock('h3') : lines(rows => rows.map(row => /^###\s/.test(row) && !/^####/.test(row) ? row.replace(PREFIX, '') : '### ' + row.replace(PREFIX, ''))),
    bold: () => mode === 'visual' ? document.execCommand('bold') : surround('**', '**', 'bold text'),
    italic: () => mode === 'visual' ? document.execCommand('italic') : surround('*', '*', 'italic text'),
    code: () => mode === 'visual' ? toggleCode() : surround('`', '`', 'code'),
    ul: () => mode === 'visual' ? document.execCommand('insertUnorderedList') : prefix('- ', false),
    ol: () => mode === 'visual' ? document.execCommand('insertOrderedList') : prefix('', true),
    quote: () => mode === 'visual' ? formatBlock('blockquote') : prefix('> ', false),
    codeblock: () => mode === 'visual' ? formatBlock('pre') : fence('```\n', '\n```'),
    hr: () => mode === 'visual' ? document.execCommand('insertHorizontalRule') : fence('---', ''),
    link: () => openLink(),
    image: () => openPicker(),
  };
  const run = name => {
    if (!commands[name]) return;
    if (mode === 'visual' && !['link', 'image'].includes(name) && (!visual.contains(getSelection().anchorNode) || getSelection().anchorNode === visual)) { visual.focus(); caretAtEnd(); }
    commands[name]();
    if (mode === 'visual' && !['link', 'image'].includes(name)) dirty = true;
    refresh();
  };
  toolbar.addEventListener('mousedown', event => { if (event.target.closest('[data-cmd]')) event.preventDefault(); });
  toolbar.addEventListener('click', event => { const button = event.target.closest('[data-cmd]'); if (button) run(button.dataset.cmd); });
  const shortcuts = event => {
    if (!(event.metaKey || event.ctrlKey) || event.altKey || event.shiftKey) return;
    const name = {b: 'bold', i: 'italic', k: 'link'}[event.key.toLowerCase()];
    if (name) { event.preventDefault(); run(name); }
  };
  textarea.addEventListener('keydown', shortcuts);
  visual.addEventListener('keydown', shortcuts);

  // Pressed state of the toolbar buttons in the Visual mode.
  const refresh = () => {
    const inside = mode === 'visual' && visual.contains(getSelection().anchorNode);
    const format = inside ? (document.queryCommandValue('formatBlock') || '').toLowerCase() : '';
    const state = name => inside && document.queryCommandState(name);
    const heading = /^h[1-6]$/.test(format);
    const pressed = {bold: !heading && state('bold'), italic: state('italic'), ul: state('insertUnorderedList'), ol: state('insertOrderedList'),
      h2: format === 'h2', h3: format === 'h3', quote: format === 'blockquote', codeblock: format === 'pre'};
    for (const button of toolbar.querySelectorAll('[data-cmd]')) {
      if (button.dataset.cmd in pressed) button.setAttribute('aria-pressed', String(Boolean(pressed[button.dataset.cmd])));
    }
  };
  document.addEventListener('selectionchange', () => { if (mode === 'visual') refresh(); });

  // Link dialog.
  const linkDialog = document.getElementById('link-dialog');
  const linkUrl = linkDialog.querySelector('[data-link-url]');
  const linkText = linkDialog.querySelector('[data-link-text]');
  let linkTarget = null, linkOriginal = '';
  const openLink = () => {
    remember();
    const node = mode === 'visual' ? getSelection().anchorNode : null;
    linkTarget = node && (node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement).closest('a');
    if (linkTarget && !visual.contains(linkTarget)) linkTarget = null;
    linkUrl.value = linkTarget ? linkTarget.getAttribute('href') : '';
    linkText.value = linkOriginal = linkTarget ? linkTarget.textContent : selectedText();
    linkDialog.querySelector('[data-link-remove]').hidden = !linkTarget;
    linkDialog.showModal();
    linkUrl.focus();
  };
  linkDialog.querySelector('[data-link-form]').addEventListener('submit', event => {
    event.preventDefault();
    const url = linkUrl.value.trim(), text = linkText.value.trim() || url;
    if (!safeUrl(url, false)) { linkUrl.setCustomValidity('Use https://, http://, mailto: or a path such as /en/about.'); linkUrl.reportValidity(); return; }
    linkDialog.close();
    restore();
    if (mode === 'markdown') { const [start, end] = savedText; replace(start, end, `[${label(text)}](${href(url)})`); return; }
    if (linkTarget) { linkTarget.setAttribute('href', url); linkTarget.textContent = text; dirty = true; }
    // An unchanged selection is linked in place, so formatting and paragraphs inside it survive.
    else if (savedRange && !savedRange.collapsed && linkText.value === linkOriginal) { document.execCommand('createLink', false, url); dirty = true; }
    else insertHtml(`<a href="${esc(url)}">${esc(text)}</a>`);
  });
  linkUrl.addEventListener('input', () => linkUrl.setCustomValidity(''));
  linkDialog.querySelector('[data-link-remove]').addEventListener('click', () => {
    linkDialog.close();
    if (linkTarget) { linkTarget.replaceWith(...linkTarget.childNodes); dirty = true; }
  });

  // Images go after the selection and never replace selected text.
  const openPicker = () => {
    remember();
    mediaPicker.open({title: 'Insert image', action: 'Insert image', withAlt: true, onPick: (item, alt) => {
      restore();
      if (mode === 'markdown') {
        const end = savedText[1], value = textarea.value;
        const before = end === 0 || value.slice(0, end).endsWith('\n\n') ? '' : (value.slice(0, end).endsWith('\n') ? '\n' : '\n\n');
        const after = value.slice(end).startsWith('\n') ? '' : '\n\n';
        replace(end, end, before + markdownImage(item.url, alt) + after);
      } else {
        getSelection().collapseToEnd();
        insertHtml(`<img src="${esc(item.url)}" alt="${esc(alt)}">`);
      }
    }});
  };

  let initial = 'visual';
  try { initial = localStorage.getItem('meteor.editorMode') === 'markdown' ? 'markdown' : 'visual'; } catch (error) { /* private window */ }
  setMode(initial);
})();

// Editor: unsaved-changes status, a warning before leaving, Ctrl+S / Cmd+S to save, and the
// language select, which opens another translation (asking first when there are unsaved edits).
(() => {
  const form = document.getElementById('translation');
  const state = document.querySelector('[data-save-state]');
  let dirty = false, leaving = false;
  if (form && state) {
    const mark = () => { if (dirty) return; dirty = true; state.textContent = 'Unsaved changes'; state.dataset.dirty = '1'; };
    form.addEventListener('input', mark);
    form.addEventListener('change', mark);
    form.addEventListener('submit', () => { leaving = true; state.textContent = 'Saving…'; state.dataset.dirty = '0'; });
    window.addEventListener('beforeunload', event => { if (dirty && !leaving) { event.preventDefault(); event.returnValue = ''; } });
    document.addEventListener('keydown', event => {
      if (!(event.ctrlKey || event.metaKey) || event.altKey || event.key.toLowerCase() !== 's') return;
      event.preventDefault();
      const buttons = [...document.querySelectorAll('[data-save-default]')];
      const button = buttons.find(item => item.offsetParent !== null) || buttons[0];
      if (button && !leaving) form.requestSubmit(button);
    });
  }
  const switcher = document.querySelector('[data-language-switch]');
  if (!switcher) return;
  const select = switcher.elements.locale;
  let current = select.value;
  switcher.querySelector('[data-language-go]').hidden = true;
  select.addEventListener('change', () => {
    if (dirty && !window.confirm('Switch language? Unsaved changes in this language will be lost.')) { select.value = current; return; }
    current = select.value; leaving = true;
    switcher.submit();
  });
})();

// Slug: generated from the title. While the base language is edited and the slug started empty,
// it follows the title until the writer edits the slug. Same rules as ContentEditor::slugFrom.
(() => {
  const field = document.querySelector('[data-slug-field]');
  if (!field) return;
  const slug = field.querySelector('input');
  const button = field.querySelector('[data-slug-generate]');
  const title = document.querySelector('#translation [name="title"]');
  const isBase = field.dataset.isBase === '1';
  const slugFrom = text => text.trim().toLowerCase().replace(/[^\p{L}\p{N}\p{M}]+/gu, '-').replace(/^-+|-+$/g, '')
    .slice(0, 80).replace(/-+$/, '');
  const source = () => (isBase && title ? title.value : field.dataset.baseTitle) || '';
  let follow = isBase && slug.value === '';
  button.hidden = false;
  button.addEventListener('click', () => {
    const value = slugFrom(source());
    if (value) { slug.value = value; slug.dispatchEvent(new Event('input', {bubbles: true})); }
    slug.focus();
  });
  slug.addEventListener('input', event => { if (event.isTrusted) follow = false; });
  if (title) title.addEventListener('input', () => { if (follow) slug.value = slugFrom(title.value); });
})();

// Sortable tables (languages, header & footer menus): drag a row by its handle, with a mouse or
// a finger, or focus the handle and use the arrow keys. Order inputs are renumbered after each move.
(() => {
  const bodies = document.querySelectorAll('tbody[data-sortable]');
  if (!bodies.length) return;
  const live = document.createElement('p');
  live.className = 'sr-only'; live.setAttribute('aria-live', 'polite');
  document.body.append(live);
  const rowsOf = body => [...body.querySelectorAll(':scope > tr[data-sortable-row]')];
  const changed = (body, row) => {
    const rows = rowsOf(body);
    rows.forEach((item, index) => { for (const input of item.querySelectorAll('[data-sort-order]')) input.value = String(index + 1); });
    const input = body.querySelector('[data-sort-order], [data-sort-key]');
    const form = input && input.form;
    if (form) form.dispatchEvent(new Event('input', {bubbles: true}));
    live.textContent = `Moved ${row.dataset.name || 'row'} to position ${rows.indexOf(row) + 1} of ${rows.length}.`;
  };
  // A click without dragging opens a Move menu, so rows can also be moved with a single pointer (WCAG 2.5.7).
  const menu = document.createElement('div');
  menu.className = 'move-menu'; menu.setAttribute('role', 'menu'); menu.hidden = true;
  document.body.append(menu);
  let menuFor = null, dragged = false;
  const closeMenu = refocus => {
    if (!menuFor) return;
    menu.hidden = true; menuFor.setAttribute('aria-expanded', 'false');
    if (refocus) menuFor.focus();
    menuFor = null;
  };
  const moveTo = (body, row, position) => {
    const others = rowsOf(body).filter(item => item !== row);
    if (position >= others.length) others[others.length - 1].after(row); else others[position].before(row);
    changed(body, row);
  };
  const openMenu = handle => {
    const body = handle.closest('tbody'), row = handle.closest('tr'), rows = rowsOf(body), index = rows.indexOf(row);
    menu.replaceChildren();
    const used = new Set([index]);
    for (const [label, position] of [['Move up', index - 1], ['Move down', index + 1], ['Move to top', 0], ['Move to bottom', rows.length - 1]]) {
      if (position < 0 || position >= rows.length || used.has(position)) continue;
      used.add(position);
      const item = document.createElement('button');
      item.type = 'button'; item.setAttribute('role', 'menuitem'); item.textContent = label;
      item.addEventListener('click', () => { moveTo(body, row, position); closeMenu(true); });
      menu.append(item);
    }
    const box = handle.getBoundingClientRect();
    menu.style.top = `${box.bottom + window.scrollY + 4}px`;
    menu.style.left = `${box.left + window.scrollX}px`;
    menu.hidden = false; menuFor = handle; handle.setAttribute('aria-expanded', 'true');
    menu.querySelector('button')?.focus();
  };
  menu.addEventListener('keydown', event => {
    const items = [...menu.querySelectorAll('button')], index = items.indexOf(document.activeElement);
    if (event.key === 'Escape') { event.preventDefault(); closeMenu(true); }
    else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); items[(index + (event.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length]?.focus(); }
    else if (event.key === 'Tab') closeMenu(false);
  });
  document.addEventListener('pointerdown', event => { if (menuFor && !menu.contains(event.target) && event.target.closest('[data-drag-handle]') !== menuFor) closeMenu(false); });
  for (const body of bodies) {
    for (const handle of body.querySelectorAll('[data-drag-handle]')) {
      handle.hidden = false;
      handle.setAttribute('aria-haspopup', 'menu'); handle.setAttribute('aria-expanded', 'false');
      handle.addEventListener('click', () => {
        if (dragged) { dragged = false; return; }
        if (menuFor === handle) closeMenu(true); else { closeMenu(false); openMenu(handle); }
      });
    }
    body.addEventListener('keydown', event => {
      const handle = event.target.closest('[data-drag-handle]');
      if (!handle || !['ArrowUp', 'ArrowDown'].includes(event.key)) return;
      event.preventDefault();
      const row = handle.closest('tr'), rows = rowsOf(body), index = rows.indexOf(row);
      const other = rows[index + (event.key === 'ArrowUp' ? -1 : 1)];
      if (!other) return;
      if (event.key === 'ArrowUp') other.before(row); else other.after(row);
      handle.focus();
      changed(body, row);
    });
    body.addEventListener('pointerdown', event => {
      const handle = event.target.closest('[data-drag-handle]');
      if (!handle || event.button !== 0) return;
      event.preventDefault();
      const row = handle.closest('tr');
      const start = rowsOf(body).indexOf(row);
      dragged = false;
      // Listen on the window: moving the row in the DOM drops any pointer capture.
      const pointer = event.pointerId;
      row.classList.add('is-dragging');
      document.body.classList.add('select-none');
      const move = moveEvent => {
        if (moveEvent.pointerId !== pointer) return;
        moveEvent.preventDefault();
        const target = document.elementFromPoint(moveEvent.clientX, moveEvent.clientY);
        const over = target && target.closest('tr[data-sortable-row]');
        if (!over || over === row || over.parentElement !== body) return;
        const box = over.getBoundingClientRect();
        if (moveEvent.clientY < box.top + box.height / 2) over.before(row); else over.after(row);
      };
      const end = endEvent => {
        if (endEvent.pointerId !== pointer) return;
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', end);
        window.removeEventListener('pointercancel', end);
        row.classList.remove('is-dragging');
        document.body.classList.remove('select-none');
        if (rowsOf(body).indexOf(row) !== start) { dragged = true; changed(body, row); }
      };
      window.addEventListener('pointermove', move);
      window.addEventListener('pointerup', end);
      window.addEventListener('pointercancel', end);
    });
  }
  // Forms with a save button that waits for a change (the language order).
  for (const form of document.querySelectorAll('form[data-save-on-change]')) {
    const button = form.querySelector('button');
    const status = form.querySelector('[data-save-status]');
    button.disabled = true;
    form.addEventListener('input', () => { button.disabled = false; if (status) status.textContent = 'Order changed. Save to apply it.'; });
  }
})();

// Sidebar: collapse to icons. The choice is kept in a cookie so the server renders it on the next page.
(() => {
  const toggle = document.querySelector('[data-nav-toggle]');
  if (!toggle) return;
  const shell = toggle.closest('[data-nav]');
  toggle.addEventListener('click', () => {
    const collapsed = shell.dataset.nav !== 'collapsed';
    shell.dataset.nav = collapsed ? 'collapsed' : 'expanded';
    const label = collapsed ? 'Expand menu' : 'Collapse menu';
    toggle.setAttribute('aria-expanded', String(!collapsed));
    toggle.setAttribute('aria-label', label); toggle.title = label;
    document.cookie = `meteor_admin_nav=${collapsed ? 'collapsed' : 'expanded'}; Path=/admin; Max-Age=31536000; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
  });
})();

// Menu "Add a page or a link" row: a page needs no URL, and its labels are optional.
for (const box of document.querySelectorAll('[data-link-kind]')) {
  const select = box.querySelector('select');
  const url = box.querySelector('input');
  const labels = [...box.closest('tr').querySelectorAll('[data-page-placeholder]')];
  const update = () => {
    const page = select.value !== '';
    url.hidden = page;
    for (const input of labels) {
      input.dataset.linkPlaceholder ??= input.placeholder;
      input.placeholder = page ? 'Optional: page title' : input.dataset.linkPlaceholder;
    }
  };
  select.addEventListener('change', update);
  update();
}

// Appearance: picking a custom color also selects the "Custom color" option.
for (const input of document.querySelectorAll('[data-custom-color]')) {
  input.addEventListener('input', () => { const radio = input.form.querySelector('input[name="theme_accent"][value="custom"]'); if (radio) radio.checked = true; });
}
