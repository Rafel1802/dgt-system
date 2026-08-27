@extends('layouts.app')

@section('title', 'Edit Popup Ad')
@section('page_title', 'Edit Popup Ad')
@section('content')
@section('back_url', route('admin.popup-ads.index'))
<div class="max-w-4xl mx-auto pb-12">
    <div class="flex items-center gap-4 mb-8">
        <h1 class="text-3xl font-black text-slate-900 dark:text-white">Edit Popup Ad</h1>
    </div>

    <form action="{{ route('admin.popup-ads.update', $popupAd) }}" method="POST" enctype="multipart/form-data" class="bento-card p-6 sm:p-8 space-y-6">
        @csrf
        @method('PUT')
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Title (Internal)</label>
                <input type="text" name="title" value="{{ old('title', $popupAd->title) }}" required class="w-full form-input bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 rounded-xl">
                @error('title') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Image (Optional - Leave blank to keep current)</label>
                <div x-data="imageUploader('{{ $popupAd->image_path ? Storage::url($popupAd->image_path) : '' }}')" class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-300 border-dashed rounded-xl dark:border-slate-700 hover:border-indigo-500 transition-colors bg-slate-50 dark:bg-slate-800/50" 
                    @dragover.prevent="dragover = true"
                    @dragleave.prevent="dragover = false"
                    @drop.prevent="handleDrop($event)"
                    @paste.window="handlePaste($event)"
                    :class="{'border-indigo-500 bg-indigo-50 dark:bg-indigo-500/10': dragover}">
                    <div class="space-y-1 text-center">
                        <template x-if="preview">
                            <div class="relative w-full mb-4 group cursor-pointer" @click="$refs.fileInput.click()">
                                <img :src="preview" class="max-h-48 mx-auto rounded-lg object-contain bg-white shadow-sm border border-slate-200 p-1">
                                <div class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity rounded-lg">
                                    <span class="text-white text-sm font-semibold">Click to Change</span>
                                </div>
                            </div>
                        </template>
                        <template x-if="!preview">
                            <svg class="mx-auto h-12 w-12 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </template>
                        <div class="flex text-sm text-slate-600 justify-center">
                            <label class="relative cursor-pointer rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                <span x-text="preview ? 'Change file' : 'Upload a file'"></span>
                                <input x-ref="fileInput" name="image" type="file" accept="image/*" class="sr-only" @change="handleFileChange($event)">
                            </label>
                            <p class="pl-1" x-show="!preview">or drag and drop</p>
                        </div>
                        <p class="text-xs text-slate-500">PNG, JPG, GIF up to 2MB. Or press CMD+V to paste screenshot.</p>
                    </div>
                </div>
                @error('image') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Interval (Minutes)</label>
                <input type="number" name="interval_minutes" value="{{ old('interval_minutes', $popupAd->interval_minutes) }}" required min="1" class="w-full form-input bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 rounded-xl">
                <p class="text-xs text-slate-500 mt-1">How often it reappears before clicking.</p>
                @error('interval_minutes') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Body Text (Optional)</label>
                <textarea name="body_text" rows="3" class="w-full form-input bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 rounded-xl">{{ old('body_text', $popupAd->body_text) }}</textarea>
                @error('body_text') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Button Text</label>
                <input type="text" name="button_text" value="{{ old('button_text', $popupAd->button_text) }}" class="w-full form-input bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 rounded-xl">
                @error('button_text') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Button Link URL</label>
                <input type="url" name="button_link" value="{{ old('button_link', $popupAd->button_link) }}" placeholder="https://" class="w-full form-input bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 rounded-xl">
                @error('button_link') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            
            <div>
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Notification Text (Optional)</label>
                <input type="text" name="notification_text" value="{{ old('notification_text', $popupAd->notification_text) }}" class="w-full form-input bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 rounded-xl">
                @error('notification_text') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Notification Icon (Optional Emoji)</label>
                <input type="text" name="notification_icon" value="{{ old('notification_icon', $popupAd->notification_icon) }}" class="w-full form-input bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 rounded-xl">
                @error('notification_icon') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            
            <div>
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Start Date/Time (Optional)</label>
                <input type="datetime-local" name="start_time" value="{{ old('start_time', $popupAd->start_time?->format('Y-m-d\TH:i')) }}" class="w-full form-input bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 rounded-xl">
                @error('start_time') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">End Date/Time (Optional)</label>
                <input type="datetime-local" name="end_time" value="{{ old('end_time', $popupAd->end_time?->format('Y-m-d\TH:i')) }}" class="w-full form-input bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 rounded-xl">
                @error('end_time') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            

        </div>

        <div class="pt-6 border-t border-slate-200 dark:border-slate-700 flex justify-end">
            <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 text-sm font-bold text-white transition-all bg-indigo-600 rounded-xl hover:bg-indigo-700 focus:ring-4 focus:ring-indigo-300 dark:focus:ring-indigo-800 shadow-lg shadow-indigo-200 dark:shadow-none hover:-translate-y-0.5">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                Update Popup Ad
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    function imageUploader(initialImage = null) {
        return {
            preview: initialImage,
            dragover: false,
            handleDrop(event) {
                this.dragover = false;
                if (event.dataTransfer.files.length > 0) {
                    let file = event.dataTransfer.files[0];
                    if (file.type.startsWith('image/')) {
                        this.setFile(file);
                    }
                }
            },
            handlePaste(event) {
                // Ignore paste if they are typing in an input field
                if (event.target.tagName === 'INPUT' || event.target.tagName === 'TEXTAREA') return;
                
                if (event.clipboardData && event.clipboardData.files.length > 0) {
                    let file = event.clipboardData.files[0];
                    if (file.type.startsWith('image/')) {
                        this.setFile(file);
                    }
                }
            },
            handleFileChange(event) {
                if (event.target.files.length > 0) {
                    this.setFile(event.target.files[0]);
                }
            },
            setFile(file) {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(file);
                this.$refs.fileInput.files = dataTransfer.files;
                
                let reader = new FileReader();
                reader.onload = (e) => {
                    this.preview = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }
    }
</script>
@endpush
