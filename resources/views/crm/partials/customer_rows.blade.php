@forelse($customers as $customer)
<tr class="hover:bg-indigo-50/20 hover:scale-[1.001] transition-all duration-150">
  <td class="px-5 py-3">
    @if($customer['link'])
      <a href="{{ $customer['link'] }}" class="font-semibold text-slate-800 hover:text-indigo-600 transition-colors">{{ $customer['name'] }}</a>
    @else
      <span class="font-semibold text-slate-800">{{ $customer['name'] }}</span>
    @endif
  </td>
  <td class="px-4 py-3 text-xs text-slate-500">
    <span class="font-medium text-slate-600">{{ $customer['email'] ?: '—' }}</span>
    @if($customer['phone'])
      <br><span class="text-slate-400 font-mono">{{ $customer['phone'] }}</span>
    @endif
  </td>
  <td class="px-4 py-3">
    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-semibold border transition-all duration-200"
          style="background:{{ $customer['source_color'] }}08; color:{{ $customer['source_color'] }}; border-color:{{ $customer['source_color'] }}25">
      @if(strtolower($customer['source']) === 'ebay')
        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.8 4l-.6 2.3c-.6-1.1-1.8-1.8-3.1-1.8-2.6 0-4.6 2.1-4.6 4.7v.1c0 2.6 2 4.7 4.6 4.7 1.3 0 2.5-.7 3.1-1.8l.6 2.3h1.8L18.8 4zm-3.5 8c-1.5 0-2.7-1.2-2.7-2.7s1.2-2.7 2.7-2.7 2.7 1.2 2.7 2.7-1.2 2.7-2.7 2.7zm-6.2.7h1.4c.5 0 .9-.4.9-.9 0-.4-.4-.7-.9-.7H9.1V6.9h1.7c.5 0 .9-.4.9-.9 0-.4-.4-.7-.9-.7H9.1V4H7.2v1.3H5.6c-.5 0-.9.4-.9.9s.4.8.9.8h1.6V11H5.4c-.5 0-.9.4-.9.9s.4.7.9.7h1.8v1.4h1.9v-1.4z"/></svg>
      @elseif(strtolower($customer['source']) === 'logistics')
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125a1.125 1.125 0 0 0 1.125-1.125V9.75M8.25 18.75h6M14.25 14.25h5.25a1.125 1.125 0 0 0 1.125-1.125v-3.026a1.125 1.125 0 0 0-.3-.774l-3.375-3.375a1.125 1.125 0 0 0-.774-.3H14.25m0 6.75V4.25a1.125 1.125 0 0 0-1.125-1.125H3.375a1.125 1.125 0 0 0-1.125 1.125v10.5a1.125 1.125 0 0 0 1.125 1.125h14.25z"/></svg>
      @else
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.905 0-5.64-.817-7.843-2.248m15.686 0a11.963 11.963 0 0 1-2.247 7.843m-13.438-7.843a11.96 11.96 0 0 0 2.247 7.843m0 0A11.95 11.95 0 0 0 12 13.5c2.905 0 5.64.817 7.843 2.248m-15.686 0A11.95 11.95 0 0 0 12 15.75c2.905 0 5.64-.817 7.843-2.248m-15.686 0a11.963 11.963 0 0 0 2.247 7.843m11.191-7.843A11.963 11.963 0 0 0 12 15.75"/></svg>
      @endif
      <span>{{ $customer['source'] }}</span>
    </span>
  </td>
  <td class="px-4 py-3">
    <div class="flex flex-wrap gap-1.5 items-center">
      @php
        $badges = $customer['status_badges'] ?? [
          ['label' => $customer['status_label'] ?? '', 'color' => $customer['status_color'] ?? '#94a3b8']
        ];
      @endphp
      @foreach($badges as $badge)
        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold border" 
              style="background:{{ $badge['color'] }}08; color:{{ $badge['color'] }}; border-color:{{ $badge['color'] }}25">
          <span class="w-1.5 h-1.5 rounded-full" style="background-color:{{ $badge['color'] }}"></span>
          {{ $badge['label'] }}
        </span>
      @endforeach
      @if($customer['occurrence_label'] ?? null)
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200/50" title="Repeat technical issue">
          🔁 {{ $customer['occurrence_label'] }}
        </span>
      @endif
    </div>
  </td>
  <td class="px-4 py-3 text-xs text-slate-500 font-medium">{{ $customer['created_date']?->format('d/m/Y') ?? '—' }}</td>
  <td class="px-4 py-3 text-xs text-slate-500 font-medium">{{ $customer['purchase_date']?->format('d/m/Y') ?? '—' }}</td>
  <td class="px-4 py-3 text-xs text-slate-600 font-semibold">{{ $customer['handler'] ?: '—' }}</td>
  <td class="px-4 py-3">
    <div class="flex justify-end gap-1">
      @if($customer['link'])
      <a href="{{ $customer['link'] }}" class="w-7 h-7 rounded-lg flex items-center justify-center border border-slate-200 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 hover:border-indigo-200 transition-all duration-150 shadow-sm" title="View Customer Details">
        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
      </a>
      @endif
    </div>
  </td>
</tr>
@empty
<tr>
  <td colspan="8" class="text-center py-16 text-slate-400">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="#cbd5e1" class="w-12 h-12 mx-auto mb-3">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
    </svg>
    No customers found. <a href="{{ route('crm.customers.create') }}" class="text-indigo-600 hover:underline">Add the first one →</a>
  </td>
</tr>
@endforelse
