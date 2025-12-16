@extends('layouts.dashboard')

@section('title', 'Inbox')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex">
            <!-- Left Nav -->
            <aside class="w-48 border-r border-gray-100 p-6 bg-white">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-semibold text-gray-900">Inbox</h2>
                    <a href="#" class="text-sm text-blue-600 hover:underline">New</a>
                </div>

                <nav class="space-y-3 text-sm">
                    <a href="#" class="flex items-center gap-3 text-blue-600 font-semibold">
                        <span class="w-3 h-3 rounded-full bg-blue-100 inline-block"></span>
                        <span>All</span>
                        <span class="ml-auto text-xs text-gray-400">{{ $messages->count() }}</span>
                    </a>
                    <a href="#" class="flex items-center gap-3 text-gray-600 hover:text-gray-900">
                        <span>Sent</span>
                    </a>
                    <a href="#" class="flex items-center gap-3 text-gray-600 hover:text-gray-900">
                        <span>Archived</span>
                    </a>
                </nav>
            </aside>

            <!-- Middle: Message list and toolbar -->
            <main class="flex-1 border-r border-gray-100">
                <div class="p-4 flex items-center justify-between border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <button class="text-sm text-gray-600 hover:text-gray-900">Archive</button>
                        <button class="text-sm text-gray-600 hover:text-gray-900">Delete</button>
                        <button class="text-sm text-gray-600 hover:text-gray-900">Print</button>
                    </div>

                    <div class="w-96">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i data-lucide="search" class="h-4 w-4 text-gray-400"></i>
                            </div>
                            <input type="text" placeholder="Type to search for the messages..." class="block w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg">
                        </div>
                    </div>
                </div>

                <div class="p-2">
                    <div class="divide-y divide-gray-100">
                        @foreach($messages as $m)
                        <div class="flex items-center gap-4 p-3 hover:bg-gray-50 {{ $loop->first ? 'bg-blue-50' : '' }}">
                            <input type="checkbox" class="h-4 w-4 text-blue-600">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold flex-shrink-0">{{ strtoupper(substr($m->name ?: 'U',0,1)) }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-3">
                                            <h3 class="text-sm font-semibold text-gray-900 truncate">{{ $m->name }}</h3>
                                            <span class="text-xs text-gray-400">· {{ $m->email }}</span>
                                        </div>
                                        <p class="text-sm text-gray-600 truncate mt-1">{{ \Illuminate\Support\Str::limit($m->message, 90) }}</p>
                                    </div>
                                    <time class="text-xs text-gray-400 ml-4">{{ $m->created_at->format('g:i A') }}</time>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </main>

            <!-- Right: Message panel (show a message in the sidebar) -->
            <aside class="w-96 p-6 bg-gray-50">
                @php
                    $sample = $messages->first();
                @endphp
                @if($sample)
                <div class="bg-white border border-gray-200 rounded-lg shadow p-6 h-full flex flex-col">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <p class="text-sm text-gray-500">From</p>
                            <p class="text-lg font-semibold text-gray-900">{{ $sample->name }} <span class="text-sm text-gray-400">&lt;{{ $sample->email }}&gt;</span></p>
                            <p class="text-sm text-gray-400">{{ $sample->created_at->toDayDateTimeString() }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.notifications.show', $sample->id) }}" class="text-sm text-gray-600 hover:underline">Open</a>
                        </div>
                    </div>

                    <h3 class="text-md font-semibold text-gray-900 mb-2">{{ $sample->subject ?: 'Message' }}</h3>

                    <div class="mt-2 text-gray-700 whitespace-pre-wrap flex-1 overflow-auto">{!! nl2br(e($sample->message)) !!}</div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <a href="mailto:{{ $sample->email }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg">Reply</a>
                        <a href="{{ route('admin.notifications') }}" class="px-4 py-2 bg-white text-gray-700 border border-gray-200 rounded-lg">Close</a>
                    </div>
                </div>
                @else
                <div class="text-center text-gray-500">No messages to show</div>
                @endif
            </aside>
        </div>
    </div>
</div>
@endsection
