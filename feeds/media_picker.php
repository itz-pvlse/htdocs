<div class="bg-[#0a0a0b] border border-white/10 rounded-3xl overflow-hidden shadow-2xl mb-8">
    <div class="p-4 border-b border-white/5 flex justify-between items-center bg-white/5">
        <div class="flex items-center gap-2">
            <i data-lucide="plus-square" class="w-4 h-4 text-blue-500"></i>
            <span class="text-[10px] font-black uppercase tracking-[0.3em] text-white">Create Post</span>
        </div>
        <button type="button" onclick="triggerNativeGallery()" class="group flex items-center gap-2 bg-blue-600 hover:bg-blue-500 px-4 py-2 rounded-xl transition-all active:scale-95">
            <i data-lucide="image" class="w-3.5 h-3.5 text-white"></i>
            <span class="text-[9px] font-black uppercase tracking-widest text-white">Gallery</span>
        </button>
    </div>

    <input type="file" id="native-gallery-input" multiple accept="image/*,video/*" class="hidden" onchange="renderGalleryPreview(event)">

    <div id="gallery-preview-grid" class="grid grid-cols-3 gap-0.5 bg-black min-h-[150px] max-h-[400px] overflow-y-auto">
        <div id="empty-gallery-state" class="col-span-3 flex flex-col items-center justify-center py-16 opacity-20">
            <div class="relative mb-4">
                <i data-lucide="layout-grid" class="w-12 h-12 text-white"></i>
                <i data-lucide="plus" class="w-5 h-5 text-blue-500 absolute -bottom-1 -right-1 bg-black rounded-full"></i>
            </div>
            <p class="text-[8px] font-black uppercase tracking-[0.4em] text-white">No Media Selected</p>
        </div>
    </div>

    <div class="p-5 bg-[#0a0a0b]">
        <textarea id="post-caption-input" 
            class="w-full bg-transparent border-none text-white text-sm focus:ring-0 placeholder:text-white/20 resize-none mb-4" 
            placeholder="What's the story with this vehicle?..." rows="3"></textarea>
        
        <div class="flex gap-3">
            <button onclick="clearGallerySelection()" class="flex-1 py-3.5 border border-white/10 text-white/40 font-black uppercase tracking-widest text-[9px] rounded-2xl hover:bg-red-500/10 hover:text-red-500 transition-all">
                Discard
            </button>
            <button id="submit-post-btn" onclick="uploadDealerPost()" class="flex-[2] py-3.5 bg-white text-black font-black uppercase tracking-[0.2em] text-[10px] rounded-2xl shadow-[0_10px_20px_rgba(255,255,255,0.1)] hover:bg-blue-500 hover:text-white transition-all disabled:opacity-50">
                Share to Feed
            </button>
        </div>
    </div>
</div>
    