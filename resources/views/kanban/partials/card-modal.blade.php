{{-- Card Detail Modal --}}
<div x-show="cardModal" x-cloak class="modal-overlay" @keydown.escape.window="closeCard()">
  <div class="modal-box" @click.stop>

    {{-- Loading state --}}
    <template x-if="!detailCard">
      <div class="flex items-center justify-center h-64">
        <svg class="w-8 h-8 animate-spin text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
      </div>
    </template>

    <template x-if="detailCard">
      <div class="flex flex-col h-full">

        {{-- Modal Header --}}
        <div class="modal-header">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <span class="badge"
                    :style="`background:${detailCard.card.label_bg}; color:${detailCard.card.label_color}`"
                    x-text="detailCard.card.label"></span>
              <span x-show="detailCard.card.sub_label" class="text-slate-400 text-xs" x-text="`→ ${detailCard.card.sub_label}`"></span>
            </div>
            <h3 class="font-display font-bold text-slate-800 text-lg leading-snug" x-text="detailCard.card.title"></h3>
            <div class="flex items-center gap-3 mt-1 text-xs text-slate-400">
              <span x-text="`#${detailCard.card.id}`"></span>
              <span>Created by <strong x-text="detailCard.card.creator?.name"></strong></span>
            </div>
          </div>
          <button @click="closeCard()" class="btn btn-secondary btn-icon flex-shrink-0">
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
          </button>
        </div>

        {{-- Modal Body --}}
        <div class="modal-body">

          {{-- Main Panel --}}
          <div class="modal-main">

            {{-- Tabs --}}
            <div class="modal-tab-bar">
              <button class="modal-tab" :class="{active: detailTab==='details'}" @click="detailTab='details'">Details</button>
              <button class="modal-tab" :class="{active: detailTab==='comments'}" @click="detailTab='comments'">
                Comments <span x-text="`(${detailCard.card.comments?.length ?? 0})`" class="text-xs opacity-60"></span>
              </button>
              <button class="modal-tab" :class="{active: detailTab==='checklist'}" @click="detailTab='checklist'">Checklist</button>
              <button class="modal-tab" :class="{active: detailTab==='files'}" @click="detailTab='files'">
                Files <span x-text="`(${detailCard.card.files?.length ?? 0})`" class="text-xs opacity-60"></span>
              </button>
            </div>

            <div x-show="detailTab==='details'">
              <div class="text-sm text-slate-600 leading-relaxed whitespace-pre-wrap mb-4 prose prose-sm max-w-none"
                   x-text="(detailCard.card.description?.replace(/<[^>]*>/g, '').replace(/\*\*/g, '') || 'No description provided.')"></div>

              {{-- Approval actions --}}
              <div x-show="detailCard.canApprove || detailCard.canReject" class="flex gap-2 mb-4 p-3 bg-amber-50 border border-amber-200 rounded-xl">
                <p class="text-xs text-amber-700 font-medium flex-1">This task is awaiting your review.</p>
                <button x-show="detailCard.canApprove" @click="approveCard(detailCard.card.id)"
                        class="btn btn-primary py-1 text-xs" style="background: linear-gradient(135deg,#10b981,#059669);">
                  ✓ Approve
                </button>
                <button x-show="detailCard.canReject" @click="showRejectModal=true"
                        class="btn btn-danger py-1 text-xs">
                  ✗ Reject
                </button>
              </div>

              {{-- Reject reason input --}}
              <div x-show="showRejectModal" class="mb-4 p-3 bg-rose-50 border border-rose-200 rounded-xl space-y-2">
                <label class="form-label text-rose-700">Rejection Reason <span class="text-red-500">*</span></label>
                <textarea x-model="rejectReason" rows="2" class="form-input text-sm" placeholder="Explain why…"></textarea>
                <div class="flex gap-2">
                  <button @click="showRejectModal=false; rejectReason=''" class="btn btn-secondary text-xs py-1">Cancel</button>
                  <button @click="rejectCard(detailCard.card.id)" class="btn btn-danger text-xs py-1">Confirm Reject</button>
                </div>
              </div>

              {{-- Rejection reason display --}}
              <div x-show="detailCard.card.rejection_reason" class="mb-4 p-3 bg-rose-50 border border-rose-200 rounded-xl">
                <p class="text-xs font-semibold text-rose-700 mb-1">❌ Rejection Reason</p>
                <p class="text-sm text-rose-600" x-text="detailCard.card.rejection_reason"></p>
              </div>
            </div>

            {{-- Comments Tab --}}
            <div x-show="detailTab==='comments'" class="space-y-3">
              <template x-for="c in detailCard.card.comments" :key="c.id">
                <div class="comment-item">
                  <img :src="c.user?.avatar_url ?? '/img/avatar.png'" :alt="c.user?.name" class="avatar avatar-sm flex-shrink-0">
                  <div class="flex-1">
                    <div :class="c.is_system ? 'comment-bubble system' : 'comment-bubble'">
                      <p class="text-xs font-semibold text-slate-700 mb-0.5" x-show="!c.is_system" x-text="c.user?.name"></p>
                      <p x-text="c.content"></p>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-0.5 ml-1" x-text="c.created_at ? new Date(c.created_at).toLocaleString() : ''"></p>
                  </div>
                </div>
              </template>

              {{-- New comment --}}
              <div class="flex gap-2 mt-2">
                <img src="{{ auth()->user()->avatar_url }}" class="avatar avatar-sm flex-shrink-0">
                <div class="flex-1 flex gap-2">
                  <textarea x-model="newComment" rows="2" class="form-input text-sm flex-1" placeholder="Write a comment…" @keydown.ctrl.enter="submitComment(detailCard.card.id)"></textarea>
                  <button @click="submitComment(detailCard.card.id)" :disabled="commentLoading" class="btn btn-primary self-end">
                    <span x-show="!commentLoading">Send</span>
                    <span x-show="commentLoading" x-cloak>…</span>
                  </button>
                </div>
              </div>
            </div>

            {{-- Checklist Tab --}}
            <div x-show="detailTab==='checklist'" class="space-y-4">
              <template x-for="(cl, ci) in detailCard.card.checklists" :key="cl.id">
                <div>
                  <div class="flex items-center gap-2 mb-2">
                    <p class="font-semibold text-sm text-slate-700 flex-1" x-text="cl.title"></p>
                    <span class="text-xs text-slate-400" x-text="`${cl.items?.filter(i=>i.is_completed).length ?? 0}/${cl.items?.length ?? 0}`"></span>
                  </div>
                  <template x-for="(item, ii) in cl.items" :key="item.id">
                    <div class="checklist-item" :class="{done: item.is_completed}">
                      <input type="checkbox" :checked="item.is_completed"
                             @change="toggleItem(detailCard.card.id, cl.id, ci, ii)"
                             class="checklist-checkbox">
                      <span class="text-sm" x-text="item.content"></span>
                    </div>
                  </template>
                  {{-- Add item --}}
                  <div class="flex gap-2 mt-2 pl-6">
                    <input type="text" :x-model="`newItemContent.${cl.id}`" x-model="newItemContent[cl.id]"
                           class="form-input text-sm flex-1" placeholder="Add item…"
                           @keydown.enter="addChecklistItem(detailCard.card.id, cl.id, ci)">
                    <button @click="addChecklistItem(detailCard.card.id, cl.id, ci)" class="btn btn-secondary text-sm py-1.5">Add</button>
                  </div>
                </div>
              </template>
              {{-- New checklist --}}
              <div class="flex gap-2 pt-2 border-t border-slate-100">
                <input type="text" x-model="newChecklistTitle" class="form-input text-sm flex-1" placeholder="New checklist group…"
                       @keydown.enter="addChecklist(detailCard.card.id)">
                <button @click="addChecklist(detailCard.card.id)" class="btn btn-secondary text-sm py-1.5">+ Group</button>
              </div>
            </div>

            {{-- Files Tab --}}
            <div x-show="detailTab==='files'">
              <template x-for="(f, fi) in detailCard.card.files" :key="f.id">
                <div class="file-item">
                  <div class="file-icon" x-text="f.icon?.toUpperCase()"></div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-700 truncate" x-text="f.original_name"></p>
                    <p class="text-xs text-slate-400" x-text="f.formatted_size"></p>
                  </div>
                  <a :href="f.url" class="btn btn-secondary py-1 text-xs" download>↓</a>
                  <button @click="deleteFile(detailCard.card.id, f.id, fi)" class="btn btn-danger py-1 text-xs">✕</button>
                </div>
              </template>

              {{-- Upload --}}
              <label class="upload-zone cursor-pointer mt-3 block" :class="{uploading: uploadLoading}">
                <input type="file" multiple class="hidden"
                       @change="uploadFiles(detailCard.card.id, $event)"
                       accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.mp4,.mov">
                <svg class="w-8 h-8 mx-auto mb-2 text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                </svg>
                <p class="text-sm text-slate-500" x-show="!uploadLoading">Click to upload files (max 10MB each)</p>
                <p class="text-sm text-indigo-500 font-medium" x-show="uploadLoading" x-cloak>Uploading…</p>
              </label>
            </div>

          </div>

          {{-- Sidebar Panel --}}
          <div class="modal-sidebar space-y-4 text-sm">

            {{-- Status --}}
            <div>
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Status</p>
              <span class="badge" :class="'badge-' + (detailCard.card.status === 'in_progress' ? 'sky' : detailCard.card.status === 'todo' ? 'slate' : detailCard.card.status === 'review' ? 'amber' : detailCard.card.status === 'approved' ? 'emerald' : detailCard.card.status === 'rejected' ? 'rose' : 'violet')"
                    x-text="detailCard.card.status?.replace('_', ' ').replace(/\b\w/g, l=>l.toUpperCase())"></span>
            </div>

            {{-- Priority --}}
            <div>
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Priority</p>
              <span class="badge" :class="detailCard.card.priority === 'urgent' ? 'badge-rose' : detailCard.card.priority === 'high' ? 'badge-amber' : detailCard.card.priority === 'medium' ? 'badge-sky' : 'badge-slate'"
                    x-text="detailCard.card.priority?.charAt(0).toUpperCase() + detailCard.card.priority?.slice(1)"></span>
            </div>

            {{-- Deadline --}}
            <div x-show="detailCard.card.deadline">
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Deadline</p>
              <p class="text-slate-700 font-medium" x-text="detailCard.card.deadline ? new Date(detailCard.card.deadline).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}) : ''"></p>
            </div>

            {{-- Assignees --}}
            <div>
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Assignees</p>
              <div class="space-y-2">
                <template x-for="a in detailCard.card.assignees" :key="a.id">
                  <div class="flex items-center gap-2">
                    <img :src="a.avatar_url" :alt="a.name" class="avatar avatar-sm">
                    <span class="text-sm text-slate-700" x-text="a.name"></span>
                  </div>
                </template>
                <p x-show="!detailCard.card.assignees?.length" class="text-xs text-slate-400">None assigned</p>
              </div>
            </div>

            {{-- Approved by --}}
            <div x-show="detailCard.card.approved_by">
              <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1">Approved by</p>
              <p class="text-sm text-emerald-700 font-medium" x-text="detailCard.card.approver?.name"></p>
            </div>

          </div>
        </div>{{-- end modal-body --}}
      </div>
    </template>
  </div>
</div>
