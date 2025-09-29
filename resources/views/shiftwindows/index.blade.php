{{-- resources/views/shiftwindows/index.blade.php --}}
<x-app-layout>
 <x-slot name="header"><h2 class="font-semibold text-xl">Duty Schedules</h2></x-slot>
 <div class="p-6 max-w-5xl mx-auto">
   <a href="{{ route('shiftwindows.create') }}" class="px-3 py-2 bg-blue-600 text-white rounded mb-3 inline-block">New Shift</a>
   <table class="min-w-full bg-white rounded shadow text-sm">
     <tr class="bg-gray-50">
       <th class="p-2">Name</th><th class="p-2">AM In</th><th class="p-2">AM Out</th>
       <th class="p-2">PM In</th><th class="p-2">PM Out</th><th class="p-2">Grace</th><th></th>
     </tr>
     @foreach($rows as $sw)
     <tr class="border-t">
       <td class="p-2">{{ $sw->name }}</td>
       <td class="p-2">{{ $sw->am_in_start }}-{{ $sw->am_in_end }}</td>
       <td class="p-2">{{ $sw->am_out_start }}-{{ $sw->am_out_end }}</td>
       <td class="p-2">{{ $sw->pm_in_start }}-{{ $sw->pm_in_end }}</td>
       <td class="p-2">{{ $sw->pm_out_start }}-{{ $sw->pm_out_end }}</td>
       <td class="p-2">{{ $sw->grace_minutes }}m</td>
       <td class="p-2">
         <a class="text-blue-600" href="{{ route('shiftwindows.edit',$sw) }}">Edit</a>
         <form action="{{ route('shiftwindows.destroy',$sw) }}" method="POST" class="inline">@csrf @method('DELETE')
            <button class="text-red-600 ml-2" onclick="return confirm('Delete?')">Delete</button>
         </form>
       </td>
     </tr>
     @endforeach
   </table>
   <div class="mt-3">{{ $rows->links() }}</div>
 </div>
</x-app-layout>
