/**
 * NATIVE GALLERY INTERFACE LOGIC
 */

function triggerNativeGallery() {
    document.getElementById('native-gallery-input').click();
}

/**
 * Updated previewFiles with Filter Logic Support
 */
function previewFiles(e) {
    const files = e.target.files;
    const gridContainer = document.getElementById('preview-grid-container');
    const filterBar = document.getElementById('media-filter-bar'); // Targeting the filter bar
    
    gridContainer.innerHTML = '';
    
    if (files.length > 0) {
        gridContainer.classList.remove('hidden');
        gridContainer.className = "grid grid-cols-3 gap-0.5 bg-black max-h-[300px] overflow-y-auto border-b border-white/5";
        if (filterBar) filterBar.classList.remove('hidden'); // Show filters when media exists
    } else {
        gridContainer.classList.add('hidden');
        if (filterBar) filterBar.classList.add('hidden');
        return;
    }

    Array.from(files).forEach((file) => {
        const isVideo = file.type.startsWith('video/');
        const type = isVideo ? 'video' : 'image';
        const url = URL.createObjectURL(file);

        const wrapper = document.createElement('div');
        // Added 'preview-item' and 'data-type' for the filter logic to work
        wrapper.className = "preview-item relative aspect-square overflow-hidden group border-[0.5px] border-white/5 animate-in zoom-in-95 duration-300";
        wrapper.dataset.type = type;
        
        if (type === 'image') {
            wrapper.innerHTML = `
                <img src="${url}" class="w-full h-full object-cover">
                <div class="absolute top-1.5 right-1.5 bg-blue-600 rounded-full p-1 shadow-lg border border-white/20">
                    <i data-lucide="check" class="w-2.5 h-2.5 text-white"></i>
                </div>
            `;
        } else if (type === 'video') {
            wrapper.innerHTML = `
                <video src="${url}" class="w-full h-full object-cover" muted loop onmouseover="this.play()" onmouseout="this.pause()"></video>
                <div class="absolute inset-0 flex items-center justify-center bg-black/20 pointer-events-none">
                    <i data-lucide="play" class="w-5 h-5 text-white fill-white opacity-80 shadow-2xl"></i>
                </div>
                <div class="absolute top-1.5 right-1.5 bg-blue-600 rounded-full p-1 shadow-lg border border-white/20">
                    <i data-lucide="check" class="w-2.5 h-2.5 text-white"></i>
                </div>
            `;
        }
        gridContainer.appendChild(wrapper);
    });

    if(window.lucide) lucide.createIcons();
}

/**
 * Filter logic to show/hide items based on type
 */
function filterPreview(type) {
    const items = document.querySelectorAll('.preview-item');
    const buttons = document.querySelectorAll('.media-filter-btn');
    
    // Update Button UI styles
    buttons.forEach(btn => {
        // Simple check to see if the button clicked matches the type
        if(btn.getAttribute('onclick').includes(`'${type}'`)) {
            btn.classList.add('text-blue-500', 'border-blue-500');
            btn.classList.remove('text-white/40');
        } else {
            btn.classList.remove('text-blue-500', 'border-blue-500');
            btn.classList.add('text-white/40');
        }
    });

    // Toggle Visibility of grid items
    items.forEach(item => {
        if (type === 'all' || item.dataset.type === type) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

function clearGallerySelection() {
    const fileInput = document.getElementById('native-gallery-input');
    const gridContainer = document.getElementById('preview-grid-container');
    const filterBar = document.getElementById('media-filter-bar');

    if(fileInput) fileInput.value = "";
    if(gridContainer) {
        gridContainer.innerHTML = '';
        gridContainer.classList.add('hidden');
    }
    if(filterBar) filterBar.classList.add('hidden');
    
    document.getElementById('post-caption-input').value = "";
}

/**
 * FINAL UPLOAD FUNCTION
 */
function uploadDealerPost() {
    const fileInput = document.getElementById('native-gallery-input');
    const caption = document.getElementById('post-caption-input').value;
    const btn = document.getElementById('submit-post-btn');

    if (!fileInput.files || fileInput.files.length === 0) return alert("Please select media first.");

    btn.disabled = true;
    btn.innerText = "Uploading...";

    const formData = new FormData();
    formData.append('caption', caption);
    
    for (let i = 0; i < fileInput.files.length; i++) {
        formData.append('post_media[]', fileInput.files[i]);
    }

    fetch('../feeds/process_new_post.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            if(typeof loadProfilePosts === 'function') {
                loadProfilePosts(window.currentDealerId); 
                clearGallerySelection();
            } else {
                location.reload();
            }
        } else {
            alert(data.error || "Upload failed");
        }
    })
    .catch(err => {
        console.error(err);
        alert("Server error during upload.");
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerText = "Share to Feed";
    });
}
