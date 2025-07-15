<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          
        </h2>
    </x-slot>
 @php
    $isDisabled = optional($poolingApiStatus)->is_polling == 1;
 @endphp
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                   <div class="row">
                        <div class="col-12">
                            <button type="button" class="btn btn-lg btn-success startPooling" {{ $isDisabled ? 'disabled' : '' }}>START</button>
                            <button type="button" class="btn btn-lg btn-danger stopPooling" {{ $isDisabled ? '' : 'disabled' }}>STOP</button>
                        </div>
                     </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="row">
                    <div class="col-12">
                       
                    </div>
                </div>
            </div>
        </div>
    </div>
         
    
  

<script>
    $(document).ready(function() {
        // Note the dot before startPooling, since it's a class selector
        $('.startPooling').on('click', function(e){
            e.preventDefault();
            $('.stopPooling').removeAttr('disabled');
            $(this).attr('disabled', true);  // disable the START button
            $.ajax({
               url: "{{ route('autodesk-api-pooling') }}", // replace with your route
               type: 'POST',
               data: {
                  isPooling: true
                },
               headers: {
                  'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
              success: function(response) {
               console.log('Polling started.');
              },
              error: function(xhr) {
              console.error('Error starting polling:', xhr.responseText);
             }
            });
        });

        $('.stopPooling').on('click', function(e){
            e.preventDefault();
            $(this).attr('disabled', true);
            $('.startPooling').attr('disabled',false);
             $.ajax({
               url: "{{ route('autodesk-api-pooling') }}", // replace with your route
               type: 'POST',
               data: {
                  isPooling: false
                },
               headers: {
                  'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
              success: function(response) {
               console.log('Polling started.');
              },
              error: function(xhr) {
              console.error('Error starting polling:', xhr.responseText);
             }
            });
        });
        
    });
</script>
</x-app-layout>
