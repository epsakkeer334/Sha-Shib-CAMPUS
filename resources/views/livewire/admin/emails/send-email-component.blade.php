@php
    $isStaff = $audience === 'staff';
    $count = $info['count'] ?? 0;
    $kindIcon = ['student' => 'ti-school', 'parent' => 'ti-users', 'staff' => 'ti-user'];
@endphp
<div class="content se-ui">
    {{-- Page header --}}
    <div class="se-head">
        <div class="min-w-0">
            <nav>
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">Communication</li>
                    <li class="breadcrumb-item active">Send Email</li>
                </ol>
            </nav>
            <h2 class="se-title"><span class="se-title-icon"><i class="ti ti-mail-forward"></i></span> Send Email</h2>
            <p class="se-lead">Send one message to chosen students, a batch, a course or a whole institute{{ $isSuperAdmin ? ' — or to institute staff' : '' }}. Each person gets their own copy, and every email is logged.</p>
        </div>
        <div class="se-head-side">
            <a href="{{ route('admin.emails.sent') }}" class="btn btn-light btn-sm"><i class="ti ti-mail-opened me-1"></i> Sent emails</a>
            @can('email_templates.manage')
                <a href="{{ route('admin.email-templates', ['template' => $isStaff ? 'announcement_staff' : 'announcement_students']) }}" class="btn btn-light btn-sm"><i class="ti ti-mail-cog me-1"></i> Templates</a>
            @endcan
        </div>
    </div>

    @unless($emailLive)
        <div class="se-note warn mb-3"><i class="ti ti-alert-triangle"></i>
            <span>Email sending is switched off{{ $isSuperAdmin ? '' : ' by the Super Admin' }}. What you send now is kept as “pending” in the Email &amp; SMS Log and goes out once sending is switched on
                @if($isSuperAdmin) in <a href="{{ route('admin.settings', ['tab' => 'email']) }}">System Settings ▸ Email</a>@endif.</span>
        </div>
    @endunless

    <div class="se-layout">
        <div class="se-main">
            {{-- 1 · Receivers --}}
            <div class="se-panel">
                <div class="se-panel-head">
                    <span class="se-step">1</span>
                    <div class="min-w-0 flex-grow-1"><h5>Who receives it</h5><p>Choose a group with the filters, or pick people one by one.</p></div>
                </div>
                <div class="se-panel-body">
                    @if($isSuperAdmin)
                        <div class="se-row">
                            <span class="se-label">Send to</span>
                            <div class="se-seg">
                                <button type="button" class="{{ !$isStaff ? 'on' : '' }}" wire:click="$set('audience', 'students')"><i class="ti ti-school"></i> Students</button>
                                <button type="button" class="{{ $isStaff ? 'on' : '' }}" wire:click="$set('audience', 'staff')"><i class="ti ti-users"></i> Institute staff</button>
                            </div>
                        </div>
                    @else
                        <div class="se-row">
                            <span class="se-label">Send to</span>
                            <span class="se-chip"><i class="ti ti-school"></i> Students of {{ $instituteName }}</span>
                        </div>
                    @endif

                    <div class="se-row">
                        <span class="se-label">Choose</span>
                        <div class="se-seg">
                            <button type="button" class="{{ $mode === 'group' ? 'on' : '' }}" wire:click="$set('mode', 'group')"><i class="ti ti-filter"></i> By group</button>
                            <button type="button" class="{{ $mode === 'selected' ? 'on' : '' }}" wire:click="$set('mode', 'selected')"><i class="ti ti-user-check"></i> {{ $isStaff ? 'Pick users' : 'Pick students' }}</button>
                        </div>
                    </div>

                    @if($mode === 'group')
                        @if($isSuperAdmin)
                            <div class="se-filter">
                                <div class="se-filter-head"><span><i class="ti ti-building"></i> Institute</span><small>{{ $institutes ? count($institutes) . ' chosen' : 'All institutes' }}</small></div>
                                <div class="se-pills">
                                    <button type="button" class="se-pill {{ !$institutes ? 'on' : '' }}" wire:click="clear('institutes')">All</button>
                                    @foreach($instituteOptions as $inst)
                                        <button type="button" class="se-pill {{ in_array($inst->id, $institutes) ? 'on' : '' }}" wire:click="toggle('institutes', {{ $inst->id }})" wire:key="si-{{ $inst->id }}" title="{{ $inst->name }}">{{ $inst->name }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(!$isStaff)
                            <div class="se-filter">
                                <div class="se-filter-head"><span><i class="ti ti-books"></i> Course</span><small>{{ $courses ? count($courses) . ' chosen' : 'All courses' }}</small></div>
                                <div class="se-pills">
                                    <button type="button" class="se-pill {{ !$courses ? 'on' : '' }}" wire:click="clear('courses')">All</button>
                                    @forelse($courseOptions as $c)
                                        <button type="button" class="se-pill {{ in_array($c->id, $courses) ? 'on' : '' }}" wire:click="toggle('courses', {{ $c->id }})" wire:key="sc-{{ $c->id }}">{{ $c->name }}</button>
                                    @empty
                                        <span class="se-empty">No courses offered yet.</span>
                                    @endforelse
                                </div>
                            </div>

                            <div class="se-filter">
                                <div class="se-filter-head"><span><i class="ti ti-users-group"></i> Batch</span><small>{{ $batches ? count($batches) . ' chosen' : 'All batches' }}</small></div>
                                <div class="se-pills is-scroll">
                                    <button type="button" class="se-pill {{ !$batches ? 'on' : '' }}" wire:click="clear('batches')">All</button>
                                    @forelse($batchOptions as $b)
                                        <button type="button" class="se-pill {{ in_array($b->id, $batches) ? 'on' : '' }} {{ $b->status ? '' : 'is-dim' }}" wire:click="toggle('batches', {{ $b->id }})" wire:key="sb-{{ $b->id }}" title="{{ $b->name }}{{ $b->status ? '' : ' (inactive)' }}">
                                            <b>{{ $b->code }}</b> {{ \Illuminate\Support\Str::limit($b->name, 28) }}@if($isSuperAdmin && !$institutes) <em>{{ optional($b->institute)->code }}</em>@endif
                                        </button>
                                    @empty
                                        <span class="se-empty">No batches for these courses.</span>
                                    @endforelse
                                </div>
                            </div>

                            <div class="se-filter-grid">
                                <div class="se-filter">
                                    <div class="se-filter-head"><span><i class="ti ti-progress-check"></i> Student status</span><small>{{ $statuses ? count($statuses) . ' chosen' : 'Any status' }}</small></div>
                                    <div class="se-pills">
                                        <button type="button" class="se-pill {{ !$statuses ? 'on' : '' }}" wire:click="clear('statuses')">Any</button>
                                        @foreach($statusOptions as $key => [$label, $colour])
                                            <button type="button" class="se-pill {{ in_array($key, $statuses) ? 'on' : '' }}" wire:click="toggle('statuses', '{{ $key }}')" wire:key="ss-{{ $key }}"><span class="se-dot bg-{{ $colour }}"></span>{{ $label }}</button>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="se-filter">
                                    <div class="se-filter-head"><span><i class="ti ti-cash"></i> Fees</span></div>
                                    <div class="se-pills">
                                        <button type="button" class="se-pill {{ $fees === '' ? 'on' : '' }}" wire:click="$set('fees', '')">Anyone</button>
                                        <button type="button" class="se-pill {{ $fees === 'unpaid' ? 'on' : '' }}" wire:click="$set('fees', 'unpaid')"><i class="ti ti-alert-circle"></i> Has unpaid fees</button>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="se-filter">
                                <div class="se-filter-head"><span><i class="ti ti-id-badge"></i> Role</span><small>{{ $roles ? count($roles) . ' chosen' : 'All roles' }}</small></div>
                                <div class="se-pills">
                                    <button type="button" class="se-pill {{ !$roles ? 'on' : '' }}" wire:click="clear('roles')">All</button>
                                    @foreach($roleOptions as $key => $label)
                                        <button type="button" class="se-pill {{ in_array($key, $roles) ? 'on' : '' }}" wire:click="toggle('roles', '{{ $key }}')" wire:key="sr-{{ $key }}">{{ $label }}</button>
                                    @endforeach
                                </div>
                            </div>
                            <label class="se-check">
                                <input type="checkbox" class="form-check-input" wire:model="activeOnly">
                                <span>Only active users <small>(inactive accounts are left out)</small></span>
                            </label>
                        @endif
                    @else
                        {{-- Pick people --}}
                        <div class="se-picker">
                            <div class="se-search">
                                <i class="ti ti-search"></i>
                                <input type="text" class="form-control" wire:model.debounce.300ms="search" placeholder="{{ $isStaff ? 'Search users by name, email or employee code…' : 'Search students by name, ER number, email or phone…' }}" aria-label="Search">
                                <span class="se-search-spin" wire:loading wire:target="search"><span class="spinner-border spinner-border-sm"></span></span>
                            </div>
                            @if(mb_strlen(trim($search)) >= 2)
                                <div class="se-results">
                                    @forelse($searchResults as $p)
                                        <button type="button" class="se-result" wire:click="pick({{ $p['id'] }})" wire:key="sp-{{ $p['id'] }}">
                                            <span class="se-avatar">{{ mb_strtoupper(mb_substr($p['name'], 0, 1)) }}</span>
                                            <span class="min-w-0 flex-grow-1"><b>{{ $p['name'] }}</b><small>{{ $p['email'] ?: 'No email' }} · {{ $p['sub'] }}</small></span>
                                            <i class="ti ti-plus"></i>
                                        </button>
                                    @empty
                                        <div class="se-empty p-2">Nobody found for “{{ $search }}”.</div>
                                    @endforelse
                                </div>
                            @endif
                            <div class="se-filter-head mt-3"><span><i class="ti ti-user-check"></i> Chosen</span>
                                <small>{{ count($selected) }} {{ \Illuminate\Support\Str::plural('person', count($selected)) }}@if($selected) · <a href="#" wire:click.prevent="clear('selected')">clear</a>@endif</small></div>
                            <div class="se-chosen">
                                @forelse($selectedPeople as $p)
                                    <span class="se-person {{ $p['email'] ? '' : 'is-bad' }}" wire:key="sx-{{ $p['id'] }}" title="{{ $p['email'] ?: 'No email address' }}">
                                        {{ $p['name'] }}
                                        <button type="button" wire:click="unpick({{ $p['id'] }})" aria-label="Remove"><i class="ti ti-x"></i></button>
                                    </span>
                                @empty
                                    <span class="se-empty">Search above and click a name to add it.</span>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    @if(!$isStaff)
                        <div class="se-row mt-3 mb-0">
                            <span class="se-label">Email goes to</span>
                            <div class="se-seg">
                                <button type="button" class="{{ $sendTo === 'student' ? 'on' : '' }}" wire:click="$set('sendTo', 'student')"><i class="ti ti-school"></i> Student</button>
                                <button type="button" class="{{ $sendTo === 'parent' ? 'on' : '' }}" wire:click="$set('sendTo', 'parent')"><i class="ti ti-users"></i> Parent</button>
                                <button type="button" class="{{ $sendTo === 'both' ? 'on' : '' }}" wire:click="$set('sendTo', 'both')"><i class="ti ti-users-plus"></i> Both</button>
                            </div>
                        </div>
                    @endif
                    @error('audience')<div class="se-note bad mt-3"><i class="ti ti-alert-circle"></i><span>{{ $message }}</span></div>@enderror
                </div>
            </div>

            {{-- 2 · Message --}}
            <div class="se-panel">
                <div class="se-panel-head">
                    <span class="se-step">2</span>
                    <div class="min-w-0 flex-grow-1"><h5>Message</h5><p>Start from a template or write your own. Placeholders are filled in for each person.</p></div>
                </div>
                <div class="se-panel-body">
                    <div class="se-templates">
                        @foreach($templates as $key => $t)
                            <button type="button" class="se-tpl {{ $templateKey === $key ? 'on' : '' }}" wire:click="useTemplate('{{ $key }}')" wire:key="st-{{ $key }}" title="{{ $t['description'] }}">
                                <i class="ti {{ $isStaff ? 'ti-speakerphone' : (str_contains($key, 'fee') ? 'ti-cash' : (str_contains($key, 'class') ? 'ti-calendar-event' : 'ti-speakerphone')) }}"></i>
                                <span><b>{{ $t['name'] }}</b><small>{{ $t['source'] === 'default' ? 'Built in' : ($t['source'] === 'institute' ? 'Institute version' : 'Group version') }}</small></span>
                            </button>
                        @endforeach
                        <button type="button" class="se-tpl {{ $templateKey === '' ? 'on' : '' }}" wire:click="blank">
                            <i class="ti ti-pencil-plus"></i><span><b>Write your own</b><small>Empty message</small></span>
                        </button>
                    </div>
                    @if($templateKey && $dirty)
                        <div class="se-hint mb-2"><i class="ti ti-info-circle"></i> Changed from the template — the changes are used for this send only.</div>
                    @endif

                    <div class="se-field">
                        <div class="se-field-label"><label for="se-subject">Subject</label><span class="se-count">{{ mb_strlen((string) $subject) }}/200</span></div>
                        <input id="se-subject" type="text" class="form-control @error('subject') is-invalid @enderror" wire:model.debounce.600ms="subject" maxlength="200" placeholder="e.g. Holiday on Friday">
                        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="se-field">
                        <div class="se-field-label"><label for="se-body">Message</label><span class="se-count">{{ mb_strlen((string) $body) }}/5000</span></div>
                        <div class="se-editor @error('body') is-invalid @enderror" data-se-target="se-body">
                            <div class="se-toolbar">
                                <button type="button" class="se-tool" data-se-wrap="**" title="Bold (select text first)"><i class="ti ti-bold"></i></button>
                                <button type="button" class="se-tool" data-se-line="- " title="Bullet point"><i class="ti ti-list"></i></button>
                                <span class="se-toolbar-hint">Blank line = new paragraph · <code>- </code> bullet · <code>**text**</code> bold</span>
                            </div>
                            <textarea id="se-body" class="se-body" rows="11" wire:model.debounce.600ms="body" maxlength="5000" placeholder="Dear {recipient_name}, …"></textarea>
                            <div class="se-phs">
                                <span class="se-phs-label"><i class="ti ti-braces"></i> Insert</span>
                                @foreach($placeholders as $name => $desc)
                                    <button type="button" class="se-ph" data-se-insert="{{ '{' . $name . '}' }}" title="{{ $desc }}">{{ '{' . $name . '}' }}</button>
                                @endforeach
                            </div>
                        </div>
                        @error('body')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @if($unknown)
                            <div class="se-note bad mt-2"><i class="ti ti-alert-circle"></i><span>Not a placeholder: <b>{{ collect($unknown)->map(fn ($u) => '{' . $u . '}')->implode(', ') }}</b> — it will be sent as typed.</span></div>
                        @endif
                        @if(!$isStaff && $fees !== 'unpaid' && str_contains($subject . ' ' . $body, '{outstanding}'))
                            <div class="se-note warn mt-2"><i class="ti ti-cash"></i>
                                <span>The message uses {outstanding}, but students with nothing to pay are included too (they would see {{ money_inr(0) }}).
                                    @if($mode === 'group')<a href="#" wire:click.prevent="$set('fees', 'unpaid')">Send only to students with unpaid fees</a>@endif</span></div>
                        @endif
                        @if(preg_match('/\[[^\]\n]{2,40}\]/', $subject . ' ' . $body))
                            <div class="se-note warn mt-2"><i class="ti ti-pencil"></i><span>The message still has text in [square brackets] from the template — replace it before sending.</span></div>
                        @endif
                    </div>

                    <div class="se-field mb-0">
                        <div class="se-field-label"><label>Button <span class="se-optional">optional</span></label></div>
                        <div class="row g-2">
                            <div class="col-md-5">
                                <div class="se-input-icon"><i class="ti ti-click"></i>
                                    <input type="text" class="form-control @error('buttonText') is-invalid @enderror" wire:model.debounce.600ms="buttonText" maxlength="60" placeholder="Button text, e.g. Open portal" aria-label="Button text"></div>
                                @error('buttonText')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-7">
                                <div class="se-input-icon"><i class="ti ti-link"></i>
                                    <input type="text" class="form-control @error('buttonUrl') is-invalid @enderror" wire:model.debounce.600ms="buttonUrl" maxlength="255" placeholder="Link, e.g. {portal_url} or https://…" aria-label="Button link"></div>
                                @error('buttonUrl')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3 · Review, preview and send --}}
        <div class="se-side">
            <div class="se-panel">
                <div class="se-panel-head is-compact">
                    <span class="se-step">3</span>
                    <div class="min-w-0 flex-grow-1"><h5>Review and send</h5></div>
                    <span class="se-busy" wire:loading><span class="spinner-border"></span></span>
                </div>
                <div class="se-panel-body">
                    <div class="se-total {{ $info['too_many'] ?? false ? 'is-bad' : '' }}">
                        <span class="se-total-num">{{ ($info['too_many'] ?? false) ? number_format($info['matched']) . '+' : number_format($count) }}</span>
                        <span class="se-total-label">{{ ($info['too_many'] ?? false) ? 'too many — max ' . number_format($max) : \Illuminate\Support\Str::plural('email', $count) . ' will be sent' }}</span>
                    </div>
                    <ul class="se-facts">
                        <li><span>{{ $isStaff ? 'Users' : 'Students' }} matching</span><b>{{ number_format($info['matched'] ?? 0) }}</b></li>
                        @if(($info['no_email'] ?? 0))<li class="warn"><span>Left out — no email address</span><b>{{ number_format($info['no_email']) }}</b></li>@endif
                        @if(($info['duplicate'] ?? 0))<li><span>Left out — same address twice</span><b>{{ number_format($info['duplicate']) }}</b></li>@endif
                        @if($isSuperAdmin && ($info['institutes'] ?? 0) > 1)<li><span>Institutes</span><b>{{ $info['institutes'] }}</b></li>@endif
                    </ul>

                    @if(!empty($info['sample']))
                        <div class="se-sample">
                            @foreach($info['sample'] as $s)
                                <div class="se-sample-row">
                                    <i class="ti {{ $kindIcon[$s['kind']] ?? 'ti-user' }}" title="{{ ucfirst($s['kind']) }}"></i>
                                    <span class="min-w-0"><b>{{ $s['name'] }}</b><small>{{ $s['email'] }}@if($s['sub']) · {{ $s['sub'] }}@endif</small></span>
                                </div>
                            @endforeach
                            @if($count > count($info['sample']))<div class="se-sample-more">+ {{ number_format($count - count($info['sample'])) }} more</div>@endif
                        </div>
                    @endif

                    @if($workerMode !== 'worker' && $count > 300)
                        <div class="se-note info mt-3"><i class="ti ti-info-circle"></i><span>Large send: emails go out one after the other in the background. For many hundreds, switching on the queue worker (System Settings ▸ Background Jobs) adds automatic retries.</span></div>
                    @endif

                    @if($confirming)
                        <div class="se-confirm">
                            <b><i class="ti ti-send"></i> Send {{ number_format($count) }} {{ \Illuminate\Support\Str::plural('email', $count) }} now?</b>
                            <p>“{{ \Illuminate\Support\Str::limit($subject, 70) }}” goes to every receiver above. This cannot be undone.</p>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-primary flex-grow-1" wire:click="send" wire:loading.attr="disabled" wire:target="send">
                                    <span wire:loading.remove wire:target="send"><i class="ti ti-send me-1"></i> Yes, send</span>
                                    <span wire:loading wire:target="send"><span class="spinner-border spinner-border-sm me-1"></span> Sending…</span>
                                </button>
                                <button type="button" class="btn btn-light" wire:click="$set('confirming', false)">Cancel</button>
                            </div>
                        </div>
                    @else
                        <button type="button" class="btn btn-primary w-100 mt-3 se-send" wire:click="review" wire:loading.attr="disabled" wire:target="review" @if(!$count || ($info['too_many'] ?? false)) disabled @endif>
                            <i class="ti ti-send me-1"></i> Send to {{ number_format($count) }} {{ \Illuminate\Support\Str::plural('person', $count) }}
                        </button>
                    @endif
                    <div class="se-hint text-center"><i class="ti ti-shield-check"></i> Every email is kept in the Email &amp; SMS Log, the send in Sent Emails and the audit trail.</div>
                </div>
            </div>

            <div class="se-panel">
                <div class="se-panel-head is-compact">
                    <div class="min-w-0 flex-grow-1">
                        <h5><i class="ti ti-eye me-1"></i> Preview</h5>
                        <p>{{ !empty($info['first']) ? 'As ' . $info['first']['name'] . ' will see it' : 'With example values' }}</p>
                    </div>
                </div>
                <div class="se-envelope">
                    <div class="se-avatar is-navy">{{ mb_strtoupper(mb_substr((string) $previewFrom, 0, 1)) ?: 'S' }}</div>
                    <div class="min-w-0">
                        <div class="se-env-subject">{{ $previewSubject ?: '(no subject)' }}</div>
                        <div class="se-env-line"><b>{{ $previewFrom }}</b> &lt;{{ $senderEmail ?: 'SMTP2GO_SENDER_EMAIL' }}&gt;</div>
                        @if(!empty($info['first']))<div class="se-env-line">To: {{ $info['first']['email'] }}</div>@endif
                        @if($previewReplyTo)<div class="se-env-line">Reply-to: {{ $previewReplyTo }}</div>@endif
                    </div>
                </div>
                <div class="se-frame">
                    @if($previewHtml)
                        <iframe class="se-preview" srcdoc="{{ $previewHtml }}" title="Email preview" sandbox=""></iframe>
                    @else
                        <div class="p-4 text-muted small">The preview could not be built.</div>
                    @endif
                </div>
                <div class="se-test">
                    <div class="se-test-label"><i class="ti ti-flask"></i> Send me a test copy</div>
                    <form wire:submit.prevent="sendTest" class="se-test-form">
                        <input type="email" class="form-control form-control-sm @error('testEmail') is-invalid @enderror" wire:model.defer="testEmail" placeholder="name@example.com" aria-label="Test address">
                        <button type="submit" class="btn btn-sm btn-dark" wire:loading.attr="disabled" wire:target="sendTest">
                            <span wire:loading.remove wire:target="sendTest">Send test</span>
                            <span wire:loading wire:target="sendTest"><span class="spinner-border spinner-border-sm me-1"></span> Sending…</span>
                        </button>
                    </form>
                    @error('testEmail')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    @if($testResult)
                        <div class="se-note {{ $testResult['ok'] ? 'ok' : 'bad' }} mt-2"><i class="ti {{ $testResult['ok'] ? 'ti-circle-check' : 'ti-alert-circle' }}"></i><span>{{ $testResult['ok'] ? '' : 'Could not send: ' }}{{ $testResult['message'] }}</span></div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <style>
        .se-ui { --se-line: #E6E8EE; --se-ink: #0F172A; --se-muted: #64748B; --se-soft: #F8FAFC; --se-accent: #F26522; --se-navy: #1E2A4A; --se-radius: 14px; }
        .se-ui .min-w-0 { min-width: 0; }
        .se-ui .se-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
        .se-ui .se-title { display: flex; align-items: center; gap: 10px; font-size: 22px; font-weight: 700; color: var(--se-ink); margin: 2px 0 4px; }
        .se-ui .se-title-icon { width: 38px; height: 38px; border-radius: 11px; display: inline-grid; place-items: center; background: linear-gradient(135deg, #2563EB, #7C3AED); color: #fff; font-size: 20px; box-shadow: 0 6px 14px rgba(79, 70, 229, .25); }
        .se-ui .se-lead { margin: 0; color: var(--se-muted); font-size: 13.5px; max-width: 760px; }
        .se-ui .se-head-side { display: flex; gap: 8px; flex-wrap: wrap; }
        .se-ui .se-head-side .btn-light { background: #fff; border: 1px solid var(--se-line); }

        .se-ui .se-note { display: flex; gap: 10px; padding: 11px 14px; border-radius: 12px; font-size: 13px; }
        .se-ui .se-note i { font-size: 17px; flex-shrink: 0; margin-top: 1px; }
        .se-ui .se-note.warn { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
        .se-ui .se-note.ok { background: #F0FDF4; color: #166534; border: 1px solid #BBF7D0; }
        .se-ui .se-note.bad { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; word-break: break-word; }
        .se-ui .se-note.info { background: #EFF6FF; color: #1E40AF; border: 1px solid #BFDBFE; }

        .se-ui .se-layout { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr); gap: 16px; align-items: start; }
        .se-ui .se-main, .se-ui .se-side { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
        .se-ui .se-side { position: sticky; top: 80px; }
        .se-ui .se-panel { background: #fff; border: 1px solid var(--se-line); border-radius: var(--se-radius); overflow: hidden; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
        .se-ui .se-panel-head { display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-bottom: 1px solid var(--se-line); }
        .se-ui .se-panel-head.is-compact { padding: 11px 16px; }
        .se-ui .se-panel-head h5 { font-size: 15.5px; font-weight: 700; color: var(--se-ink); margin: 0; }
        .se-ui .se-panel-head p { margin: 1px 0 0; font-size: 12.5px; color: var(--se-muted); }
        .se-ui .se-panel-body { padding: 16px 20px 18px; }
        .se-ui .se-step { width: 28px; height: 28px; flex-shrink: 0; border-radius: 50%; display: grid; place-items: center; background: #EEF2FF; color: #4338CA; font-weight: 700; font-size: 13px; }
        .se-ui .se-busy .spinner-border { width: 16px; height: 16px; border-width: 2px; color: #94A3B8; }

        /* Receivers */
        .se-ui .se-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 14px; }
        .se-ui .se-label { width: 104px; flex-shrink: 0; font-size: 12.5px; font-weight: 600; color: var(--se-muted); }
        .se-ui .se-seg { display: inline-flex; padding: 3px; border-radius: 11px; background: #F1F5F9; gap: 2px; flex-wrap: wrap; }
        .se-ui .se-seg button { border: 0; background: transparent; padding: 6px 13px; border-radius: 8px; font-size: 13px; font-weight: 600; color: #475569; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
        .se-ui .se-seg button.on { background: #fff; color: var(--se-ink); box-shadow: 0 1px 3px rgba(15, 23, 42, .12); }
        .se-ui .se-seg button.on i { color: var(--se-accent); }
        .se-ui .se-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; background: #F1F5F9; font-size: 13px; font-weight: 600; color: #334155; }
        .se-ui .se-filter { padding: 12px 0; border-top: 1px dashed var(--se-line); }
        .se-ui .se-filter-grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 0 20px; }
        .se-ui .se-filter-head { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; margin-bottom: 8px; }
        .se-ui .se-filter-head span { font-size: 13px; font-weight: 600; color: var(--se-ink); display: inline-flex; align-items: center; gap: 6px; }
        .se-ui .se-filter-head span i { color: #94A3B8; font-size: 15px; }
        .se-ui .se-filter-head small { font-size: 11.5px; color: var(--se-muted); }
        .se-ui .se-pills { display: flex; flex-wrap: wrap; gap: 6px; }
        .se-ui .se-pills.is-scroll { max-height: 132px; overflow-y: auto; padding-right: 4px; }
        .se-ui .se-pill { display: inline-flex; align-items: center; gap: 5px; max-width: 100%; padding: 5px 11px; border: 1px solid var(--se-line); border-radius: 999px; background: #fff; font-size: 12.5px; color: #334155; line-height: 18px; transition: background .12s, border-color .12s; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .se-ui .se-pill:hover { border-color: #CBD5E1; background: var(--se-soft); }
        .se-ui .se-pill.on { background: #FFF1E8; border-color: #FDBA8C; color: #9A3412; font-weight: 600; }
        .se-ui .se-pill.is-dim { opacity: .65; }
        .se-ui .se-pill b { font-weight: 700; } .se-ui .se-pill em { font-style: normal; font-size: 10.5px; color: #94A3B8; }
        .se-ui .se-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
        .se-ui .se-empty { font-size: 12.5px; color: #94A3B8; }
        .se-ui .se-check { display: flex; align-items: center; gap: 8px; margin: 4px 0 0; font-size: 13px; color: var(--se-ink); cursor: pointer; }
        .se-ui .se-check small { color: var(--se-muted); }
        .se-ui .se-check .form-check-input { margin: 0; }

        .se-ui .se-picker { padding-top: 12px; border-top: 1px dashed var(--se-line); }
        .se-ui .se-search { position: relative; }
        .se-ui .se-search > i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 16px; }
        .se-ui .se-search .form-control { padding-left: 36px; padding-right: 36px; }
        .se-ui .se-search-spin { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #94A3B8; }
        .se-ui .se-results { margin-top: 6px; border: 1px solid var(--se-line); border-radius: 12px; padding: 4px; max-height: 300px; overflow-y: auto; box-shadow: 0 8px 20px rgba(15, 23, 42, .06); }
        .se-ui .se-result { width: 100%; display: flex; align-items: center; gap: 10px; padding: 7px 8px; border: 0; border-radius: 9px; background: transparent; text-align: left; }
        .se-ui .se-result:hover { background: var(--se-soft); }
        .se-ui .se-result b { display: block; font-size: 13px; color: var(--se-ink); }
        .se-ui .se-result small { display: block; font-size: 11.5px; color: var(--se-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .se-ui .se-result > i { color: var(--se-accent); font-size: 17px; }
        .se-ui .se-avatar { width: 32px; height: 32px; flex-shrink: 0; border-radius: 50%; display: grid; place-items: center; background: #EEF2FF; color: #4338CA; font-weight: 700; font-size: 13px; }
        .se-ui .se-avatar.is-navy { width: 36px; height: 36px; background: var(--se-navy); color: #fff; font-size: 15px; }
        .se-ui .se-chosen { display: flex; flex-wrap: wrap; gap: 6px; }
        .se-ui .se-person { display: inline-flex; align-items: center; gap: 4px; padding: 4px 4px 4px 11px; border-radius: 999px; background: #EEF2FF; color: #3730A3; font-size: 12.5px; font-weight: 600; }
        .se-ui .se-person.is-bad { background: #FEF2F2; color: #991B1B; }
        .se-ui .se-person button { width: 20px; height: 20px; border: 0; border-radius: 50%; background: rgba(255, 255, 255, .7); color: inherit; display: grid; place-items: center; font-size: 12px; padding: 0; }

        /* Message */
        .se-ui .se-templates { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; margin-bottom: 14px; }
        .se-ui .se-tpl { display: flex; align-items: center; gap: 10px; padding: 9px 11px; border: 1px solid var(--se-line); border-radius: 12px; background: #fff; text-align: left; transition: border-color .12s, background .12s; }
        .se-ui .se-tpl:hover { border-color: #CBD5E1; }
        .se-ui .se-tpl > i { width: 30px; height: 30px; flex-shrink: 0; border-radius: 9px; display: grid; place-items: center; background: #F1F5F9; color: #475569; font-size: 16px; }
        .se-ui .se-tpl b { display: block; font-size: 12.5px; color: var(--se-ink); line-height: 1.3; }
        .se-ui .se-tpl small { display: block; font-size: 11px; color: var(--se-muted); }
        .se-ui .se-tpl.on { border-color: #FDBA8C; background: #FFF8F3; box-shadow: 0 0 0 3px rgba(242, 101, 34, .08); }
        .se-ui .se-tpl.on > i { background: var(--se-accent); color: #fff; }
        .se-ui .se-field { margin-bottom: 16px; }
        .se-ui .se-field-label { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; margin-bottom: 6px; }
        .se-ui .se-field-label label { font-size: 13px; font-weight: 600; color: var(--se-ink); margin: 0; }
        .se-ui .se-count { font-size: 11px; color: #94A3B8; font-variant-numeric: tabular-nums; }
        .se-ui .se-optional { margin-left: 4px; padding: 1px 6px; border-radius: 6px; background: #F1F5F9; color: var(--se-muted); font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
        .se-ui .se-hint { font-size: 12px; color: var(--se-muted); margin-top: 8px; }
        .se-ui .se-input-icon { position: relative; }
        .se-ui .se-input-icon > i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 16px; pointer-events: none; }
        .se-ui .se-input-icon .form-control { padding-left: 34px; }
        .se-ui .se-editor { border: 1px solid #D7DCE5; border-radius: 12px; overflow: hidden; background: #fff; transition: border-color .15s, box-shadow .15s; }
        .se-ui .se-editor:focus-within { border-color: #F8A77F; box-shadow: 0 0 0 3px rgba(242, 101, 34, .12); }
        .se-ui .se-editor.is-invalid { border-color: #F87171; }
        .se-ui .se-toolbar { display: flex; align-items: center; gap: 4px; padding: 6px 8px; background: var(--se-soft); border-bottom: 1px solid var(--se-line); flex-wrap: wrap; }
        .se-ui .se-tool { width: 30px; height: 28px; display: grid; place-items: center; border: 1px solid transparent; border-radius: 7px; background: transparent; color: #334155; font-size: 16px; }
        .se-ui .se-tool:hover { background: #fff; border-color: var(--se-line); }
        .se-ui .se-toolbar-hint { margin-left: auto; font-size: 11.5px; color: #94A3B8; padding-right: 4px; }
        .se-ui .se-toolbar-hint code { font-size: 11px; color: #475569; background: #EEF2F7; padding: 0 4px; border-radius: 4px; }
        .se-ui .se-body { display: block; width: 100%; border: 0; outline: 0; resize: vertical; padding: 12px 14px; min-height: 220px; font-family: 'IBM Plex Mono', ui-monospace, Consolas, monospace; font-size: 13px; line-height: 21px; color: var(--se-ink); background: #fff; }
        .se-ui .se-phs { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 9px 10px; border-top: 1px solid var(--se-line); background: var(--se-soft); }
        .se-ui .se-phs-label { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 700; color: var(--se-muted); margin-right: 2px; }
        .se-ui .se-ph { border: 1px solid #DDE3FB; background: #fff; color: #4338CA; border-radius: 7px; padding: 2px 8px; font-size: 11.5px; font-family: ui-monospace, Consolas, monospace; }
        .se-ui .se-ph:hover { background: #EEF2FF; border-color: #A5B4FC; }

        /* Review */
        .se-ui .se-total { display: flex; align-items: baseline; gap: 10px; padding: 14px 16px; border-radius: 12px; background: linear-gradient(135deg, #F5F7FF, #FFF7F2); border: 1px solid #E7E9F7; }
        .se-ui .se-total.is-bad { background: #FEF2F2; border-color: #FECACA; }
        .se-ui .se-total-num { font-size: 30px; font-weight: 800; line-height: 1; color: var(--se-ink); font-variant-numeric: tabular-nums; }
        .se-ui .se-total.is-bad .se-total-num { color: #B91C1C; }
        .se-ui .se-total-label { font-size: 13px; color: var(--se-muted); font-weight: 600; }
        .se-ui .se-facts { list-style: none; margin: 10px 0 0; padding: 0; }
        .se-ui .se-facts li { display: flex; justify-content: space-between; gap: 8px; padding: 6px 2px; font-size: 12.5px; color: #475569; border-bottom: 1px solid #F1F5F9; }
        .se-ui .se-facts li b { color: var(--se-ink); font-variant-numeric: tabular-nums; }
        .se-ui .se-facts li.warn, .se-ui .se-facts li.warn b { color: #B45309; }
        .se-ui .se-sample { margin-top: 10px; border: 1px solid var(--se-line); border-radius: 12px; padding: 4px 10px; }
        .se-ui .se-sample-row { display: flex; align-items: center; gap: 9px; padding: 6px 0; border-bottom: 1px solid #F1F5F9; }
        .se-ui .se-sample-row:last-child { border-bottom: 0; }
        .se-ui .se-sample-row > i { color: #94A3B8; font-size: 15px; }
        .se-ui .se-sample-row b { display: block; font-size: 12.5px; color: var(--se-ink); }
        .se-ui .se-sample-row small { display: block; font-size: 11.5px; color: var(--se-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .se-ui .se-sample-more { padding: 6px 0 4px; font-size: 12px; color: var(--se-muted); font-weight: 600; }
        .se-ui .se-send { padding: 10px; font-weight: 600; }
        .se-ui .se-confirm { margin-top: 14px; padding: 14px; border-radius: 12px; background: #FFF8F3; border: 1px solid #FED7C3; }
        .se-ui .se-confirm b { display: flex; align-items: center; gap: 6px; color: #9A3412; font-size: 14px; }
        .se-ui .se-confirm p { margin: 4px 0 10px; font-size: 12.5px; color: #7C2D12; word-break: break-word; }

        /* Preview */
        .se-ui .se-envelope { display: flex; gap: 12px; align-items: flex-start; padding: 12px 16px; border-bottom: 1px solid var(--se-line); }
        .se-ui .se-env-subject { font-size: 14px; font-weight: 700; color: var(--se-ink); word-break: break-word; }
        .se-ui .se-env-line { font-size: 12px; color: var(--se-muted); word-break: break-word; }
        .se-ui .se-env-line b { color: #334155; }
        .se-ui .se-frame { background: #EEF0F6; }
        .se-ui .se-preview { display: block; width: 100%; height: 520px; border: 0; background: #EEF0F6; }
        .se-ui .se-test { padding: 12px 16px 14px; border-top: 1px solid var(--se-line); background: var(--se-soft); }
        .se-ui .se-test-label { font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
        .se-ui .se-test-form { display: flex; gap: 8px; }
        .se-ui .se-test-form .form-control { flex: 1 1 auto; min-width: 0; }
        .se-ui .se-test-form .btn { flex-shrink: 0; }

        @media (max-width: 1199.98px) {
            .se-ui .se-layout { grid-template-columns: minmax(0, 1fr); }
            .se-ui .se-side { position: static; }
        }
        @media (max-width: 767.98px) {
            .se-ui .se-filter-grid { grid-template-columns: minmax(0, 1fr); }
            .se-ui .se-label { width: 100%; }
            .se-ui .se-row { gap: 6px; }
        }
        @media (max-width: 575.98px) {
            .se-ui .se-panel-head, .se-ui .se-panel-body { padding-left: 14px; padding-right: 14px; }
            .se-ui .se-toolbar-hint { display: none; }
            .se-ui .se-seg { width: 100%; } .se-ui .se-seg button { flex: 1; justify-content: center; padding: 6px 8px; }
            .se-ui .se-preview { height: 460px; }
        }
    </style>

    <script>
        // Message editor helpers: insert a placeholder, wrap the selection in **bold**, or start the line with "- ".
        (function () {
            if (window.__seChips) return;
            window.__seChips = true;
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-se-insert], [data-se-wrap], [data-se-line]');
                if (!btn) return;
                var wrap = btn.closest('[data-se-target]');
                var box = wrap && document.getElementById(wrap.dataset.seTarget);
                if (!box) return;
                var v = box.value, start = box.selectionStart ?? v.length, end = box.selectionEnd ?? start, text, caretStart, caretEnd;
                if (btn.dataset.seWrap) {
                    var mark = btn.dataset.seWrap, picked = v.slice(start, end) || 'text';
                    text = mark + picked + mark;
                    caretStart = start + mark.length; caretEnd = caretStart + picked.length;
                } else if (btn.dataset.seLine) {
                    var lineStart = v.lastIndexOf('\n', start - 1) + 1;
                    start = end = lineStart; text = btn.dataset.seLine;
                    caretStart = caretEnd = lineStart + text.length;
                } else {
                    text = btn.dataset.seInsert;
                    caretStart = caretEnd = start + text.length;
                }
                box.value = v.slice(0, start) + text + v.slice(end);
                box.focus();
                box.selectionStart = caretStart; box.selectionEnd = caretEnd;
                box.dispatchEvent(new Event('input', { bubbles: true }));
            });
        })();
    </script>
</div>
