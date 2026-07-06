<div>
    <label class="block text-sm font-medium mb-1">Story Title *</label>
    <input type="text" name="title" value="{{ old('title', $story?->title) }}" required
           class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none"
           placeholder="e.g. जादुई जंगल की कहानी">
</div>

<div>
    <label class="block text-sm font-medium mb-1">Short Description</label>
    <textarea name="description" rows="3"
              class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none"
              placeholder="What is the story about...">{{ old('description', $story?->description) }}</textarea>
</div>

@php($storyBody = old('body', $story ? optional($story->parts->first())->body : ''))
<div>
    <label class="block text-sm font-medium mb-1">Story Text (poori kahani) *</label>
    <textarea name="body" rows="12" required
              class="w-full rounded-lg border border-slate-300 px-3 py-2 kahani-text focus:ring-2 focus:ring-rose-400 focus:outline-none"
              placeholder="Yahan poori kahani likho ya paste karo. Ye seedhe cards me tod di jaayegi.">{{ $storyBody }}</textarea>
    <p class="text-xs text-slate-500 mt-1">Har story ka 1 hi part hota hai — poora text yahan daalo. Save karte hi cards editor khul jaayega.</p>
</div>

<p class="text-xs text-slate-500 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
    🖼️ Cover image story save karne ke baad uske page par <b>"Upload Cover Image"</b> se daalo (9:16, reel ka cover).
</p>

<div class="grid sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium mb-1">🌐 Language (bhasha)</label>
        <select name="language" id="formLanguage" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
            <option value="hindi" @selected(old('language', $story?->language ?? 'hindi') === 'hindi')>हिंदी Hindi</option>
            <option value="gujarati" @selected(old('language', $story?->language) === 'gujarati')>ગુજરાતી Gujarati</option>
            <option value="hinglish" @selected(old('language', $story?->language) === 'hinglish')>Hindi-English (Roman)</option>
        </select>
        <p class="text-xs text-slate-500 mt-1">AI isi bhasha me kahani banayega; cards bhi isi me.</p>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Status</label>
        <select name="status" class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-rose-400 focus:outline-none">
            <option value="published" @selected(old('status', $story?->status) === 'published')>Published</option>
            <option value="draft" @selected(old('status', $story?->status ?? '') === 'draft')>Draft</option>
        </select>
    </div>
</div>
