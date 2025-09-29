{{-- resources/views/attendance/editor/index.blade.php --}}
<x-app-layout>
 <x-slot name="header"><h2 class="font-semibold text-xl">Edit Attendance</h2></x-slot>
 <div class="p-6 max-w-3xl mx-auto bg-white rounded shadow">
   <form method="GET" action="" onsubmit="if(u.value && d.value){window.location='/attendance/editor/'+u.value+'/'+d.value; return false;}">
     <label>User
       <select id="u" class="border rounded px-2 py-1">
         <option value="">-- choose --</option>
         @foreach($users as $u)
           <option value="{{ $u->id }}">{{ $u->name }}</option>
         @endforeach
       </select>
     </label>
     <label class="ml-3">Date <input id="d" type="date" class="border rounded px-2 py-1"></label>
     <button class="px-3 py-2 bg-blue-600 text-white rounded ml-3">Open</button>
   </form>
 </div>
</x-app-layout>
