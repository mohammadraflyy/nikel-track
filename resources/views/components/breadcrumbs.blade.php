@if(!empty($links))
<nav class="flex mb-4" aria-label="Breadcrumb">
    <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
        @foreach($links as $index => $link)
            @if($loop->last)
                <li aria-current="page">
                    <div class="flex items-center">
                        @if(!$loop->first)
                        <svg class="rtl:rotate-180 w-3 h-3 text-gray-400 mx-1 dark:text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
                        </svg>
                        @endif
                        <span class="ms-1 text-sm font-medium text-gray-500 md:ms-2 dark:text-gray-400">{{ $link['text'] }}</span>
                    </div>
                </li>
            @else
                <li class="inline-flex items-center">
                    @if(!$loop->first)
                    <svg class="rtl:rotate-180 w-3 h-3 text-gray-400 mx-1 dark:text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
                    </svg>
                    @endif
                    <a href="{{ $link['url'] }}" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-gray-600 dark:text-gray-300 dark:hover:text-white">
                        {{ $link['text'] }}
                    </a>
                </li>
            @endif
        @endforeach
    </ol>
</nav>
@endif