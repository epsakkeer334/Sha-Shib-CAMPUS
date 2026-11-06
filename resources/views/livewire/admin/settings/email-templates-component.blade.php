@php
    $allItems = $list->flatten(1);
    $customCount = $allItems->where('own', true)->count();
    $offCount = $allItems->where('on', false)->count();
    $isInstituteScope = $scope !== '';
@endphp
<div class="content et-ui">
    {{-- Page header --}}
    <div class="et-head">
        <div class="min-w-0">
            <nav>
                <ol class="breadcrumb mb-1 sl-crumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="ti ti-smart-home"></i></a></li>
                    <li class="breadcrumb-item">System</li>
                    <li class="breadcrumb-item active">Email Templates</li>
                </ol>
            </nav>
            <h2 class="et-title"><span class="et-title-icon"><i class="ti ti-mail-cog"></i></span> Email Templates</h2>
            <p class="et-lead">The wording of every email students and staff receive. Emails are sent through SMTP2GO in the background.</p>
        </div>
        <div class="et-head-side">
            @if($isSuperAdmin)
                <label class="et-scope">
                    <span class="et-scope-label"><i class="ti ti-building"></i> Editing for</span>
                    <select class="form-select form-select-sm" wire:model="scope" aria-label="Edit templates for">
                        <option value="">All institutes (group version)</option>
                        @foreach($institutes as $inst)<option value="{{ $inst->id }}">{{ $inst->name }}</option>@endforeach
                    </select>
                </label>
            @else
                <span class="et-chip"><i class="ti ti-building"></i> {{ $scopeLabel }}</span>
            @endif
        </div>
    </div>

    {{-- Summary strip --}}
    <div class="et-stats">
        <div class="et-stat"><span class="et-stat-icon"><i class="ti ti-template"></i></span><span><b>{{ $allItems->count() }}</b><small>Templates</small></span></div>
        <div class="et-stat"><span class="et-stat-icon is-orange"><i class="ti ti-pencil"></i></span><span><b>{{ $customCount }}</b><small>{{ $isInstituteScope ? 'Institute versions' : 'Group versions' }}</small></span></div>
        <div class="et-stat"><span class="et-stat-icon is-red"><i class="ti ti-mail-off"></i></span><span><b>{{ $offCount }}</b><small>Switched off</small></span></div>
        <div class="et-stat"><span class="et-stat-icon {{ $emailLive ? 'is-green' : 'is-grey' }}"><i class="ti {{ $emailLive ? 'ti-send' : 'ti-send-off' }}"></i></span>
            <span><b class="et-live {{ $emailLive ? 'on' : 'off' }}">{{ $emailLive ? 'On' : 'Off' }}</b><small>Email sending</small></span></div>
    </div>

    @unless($emailLive)
        <div class="et-note warn mb-3"><i class="ti ti-alert-triangle"></i>
            <span>Email sending is switched off{{ $isSuperAdmin ? '' : ' by the Super Admin' }}. Emails are kept as “pending” in the Email &amp; SMS Log until it is switched on
                @if($isSuperAdmin) in <a href="{{ route('admin.settings', ['tab' => 'email']) }}">System Settings ▸ Email</a>@endif.</span>
        </div>
    @endunless

    <div class="et-layout">
        {{-- Template list --}}
        <div class="et-side">
            <div class="et-panel et-list" id="et-menu">
                <div class="et-menu-head">
                    <span class="et-panel-title p-0">Templates <em>{{ $allItems->count() }}</em></span>
                    <span class="et-menu-tools" wire:ignore>
                        <button type="button" data-et-groups="open" title="Expand all"><i class="ti ti-arrows-vertical"></i></button>
                        <button type="button" data-et-groups="close" title="Collapse all"><i class="ti ti-fold"></i></button>
                    </span>
                </div>
                <div class="et-menu-search" wire:ignore>
                    <i class="ti ti-search"></i>
                    <input type="search" id="et-menu-filter" placeholder="Find a template…" aria-label="Find a template" autocomplete="off">
                </div>
                @foreach($groups as $groupKey => [$groupLabel, $groupIcon])
                    @if($list->has($groupKey))
                        @php
                            $groupItems = $list->get($groupKey);
                            $hasActive = $groupItems->contains('key', $selectedKey);
                            $groupOff = $groupItems->where('on', false)->count();
                        @endphp
                        <details class="et-group-box {{ $hasActive ? 'has-active' : '' }}" data-et-group="{{ $groupKey }}" wire:key="etg-{{ $groupKey }}" wire:ignore.self @if($hasActive) open @endif>
                            <summary class="et-group-head">
                                <span class="et-group-ico"><i class="{{ $groupIcon }}"></i></span>
                                <span class="et-group-name">{{ $groupLabel }}</span>
                                @if($groupOff)<span class="et-group-off" title="{{ $groupOff }} switched off">{{ $groupOff }} off</span>@endif
                                <span class="et-group-count">{{ $groupItems->count() }}</span>
                                <i class="ti ti-chevron-down et-group-chev"></i>
                            </summary>
                            <div class="et-group-items">
                                @foreach($groupItems as $item)
                                    <button type="button" class="et-item {{ $item['key'] === $selectedKey ? 'is-active' : '' }}" wire:click="select('{{ $item['key'] }}')" wire:key="eti-{{ $item['key'] }}" data-et-name="{{ mb_strtolower($item['name'] . ' ' . $item['description']) }}">
                                        <span class="et-dot {{ $item['on'] ? 'on' : 'off' }}" title="{{ $item['on'] ? 'Sending' : 'Switched off' }}"></span>
                                        <span class="min-w-0 flex-grow-1">
                                            <span class="et-item-name">{{ $item['name'] }}</span>
                                            <span class="et-item-sub">
                                                @if($item['source'] === 'institute')<span class="et-tag inst">Institute</span>
                                                @elseif($item['source'] === 'group')<span class="et-tag group">Group</span>
                                                @else<span class="et-tag def">Default</span>@endif
                                                @if(!$item['on'])<span class="et-tag off">Off</span>@endif
                                            </span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </details>
                    @endif
                @endforeach
                <div class="et-menu-empty" id="et-menu-empty" hidden>No template matches.</div>
                <div class="et-legend">
                    <span><span class="et-tag def">Default</span> built in</span>
                    <span><span class="et-tag group">Group</span> all institutes</span>
                    <span><span class="et-tag inst">Institute</span> own version</span>
                </div>
            </div>

            {{-- Small screens: pick the template from a list --}}
            <div class="et-picker">
                <label class="form-label" for="et-picker">Template</label>
                <select id="et-picker" class="form-select" wire:change="select($event.target.value)">
                    @foreach($groups as $groupKey => [$groupLabel, $groupIcon])
                        @if($list->has($groupKey))
                            <optgroup label="{{ $groupLabel }}">
                                @foreach($list->get($groupKey) as $item)
                                    <option value="{{ $item['key'] }}" @if($item['key'] === $selectedKey) selected @endif>{{ $item['name'] }}{{ $item['on'] ? '' : ' (off)' }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Editor --}}
        <div class="et-main">
            <form wire:submit.prevent="save" class="et-panel" wire:key="et-form-{{ $selectedKey }}-{{ $scope }}">
                <div class="et-panel-head">
                    <div class="min-w-0">
                        <div class="et-kicker">
                            <i class="{{ $groups[$entry['group']][1] ?? 'ti ti-mail' }}"></i> {{ $groups[$entry['group']][0] ?? '' }}
                            <span class="et-sep">•</span>
                            <i class="ti {{ ['student' => 'ti-school', 'staff' => 'ti-users'][$entry['audience']] ?? 'ti-user' }}"></i> {{ ['student' => 'Sent to the student', 'staff' => 'Sent to institute staff'][$entry['audience']] ?? 'Sent to the person chosen' }}
                        </div>
                        <h5 class="et-name">{{ $entry['name'] }}</h5>
                        <p class="et-desc">{{ $entry['description'] }}</p>
                    </div>
                    <label class="et-toggle" title="Off: this email is not sent {{ $isInstituteScope ? 'for ' . $scopeLabel : 'unless an institute has its own version switched on' }}.">
                        <span class="et-switch"><input type="checkbox" wire:model.defer="active"><span></span></span>
                        <span><b>Send this email</b><small>Takes effect when saved</small></span>
                    </label>
                </div>

                {{-- Which version is being edited --}}
                <div class="et-version {{ $hasOwn ? 'is-own' : '' }}">
                    <i class="ti {{ $hasOwn ? 'ti-pencil' : ($current['source'] === 'group' ? 'ti-world' : 'ti-file-text') }}"></i>
                    <span class="min-w-0 flex-grow-1">
                        @if($hasOwn)
                            <b>{{ $isInstituteScope ? 'Institute version' : 'Group version' }}</b> · edited {{ optional($ownRow->updated_at)->diffForHumans() }}
                        @elseif($current['source'] === 'group')
                            <b>Using the group version.</b> Saving creates a version for {{ $scopeLabel }}.
                        @else
                            <b>Using the built-in default.</b> Saving creates {{ $isInstituteScope ? 'a version for ' . $scopeLabel : 'the group version for all institutes' }}.
                        @endif
                    </span>
                    @if($hasOwn)
                        @if($confirmReset)
                            <span class="et-confirm">
                                {{ $isInstituteScope ? 'Delete this version and use the group one?' : 'Delete and use the built-in default?' }}
                                <button type="button" class="btn btn-sm btn-danger" wire:click="resetToDefault">Yes, reset</button>
                                <button type="button" class="btn btn-sm btn-light" wire:click="$set('confirmReset', false)">Cancel</button>
                            </span>
                        @else
                            <button type="button" class="btn btn-sm et-btn-ghost" wire:click="$set('confirmReset', true)"><i class="ti ti-arrow-back-up me-1"></i>{{ $isInstituteScope ? 'Use group version' : 'Reset to default' }}</button>
                        @endif
                    @endif
                </div>

                <div class="et-panel-body">
                    {{-- Subject --}}
                    <div class="et-field">
                        <div class="et-field-label">
                            <label for="et-subject">Subject</label>
                            <span class="et-count">{{ mb_strlen((string) $subject) }}/200</span>
                        </div>
                        <input id="et-subject" type="text" class="form-control @error('subject') is-invalid @enderror" wire:model.debounce.600ms="subject" maxlength="200">
                        @error('subject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    {{-- Message --}}
                    <div class="et-field">
                        <div class="et-field-label">
                            <label for="et-body">Message</label>
                            <span class="et-count">{{ mb_strlen((string) $body) }}/5000</span>
                        </div>
                        <div class="et-editor @error('body') is-invalid @enderror" data-et-target="et-body">
                            <div class="et-toolbar">
                                <button type="button" class="et-tool" data-et-wrap="**" title="Bold (select text first)"><i class="ti ti-bold"></i></button>
                                <button type="button" class="et-tool" data-et-line="- " title="Bullet point"><i class="ti ti-list"></i></button>
                                <span class="et-toolbar-hint">Blank line = new paragraph · <code>- </code> bullet · <code>**text**</code> bold</span>
                            </div>
                            <textarea id="et-body" class="et-body" rows="12" wire:model.debounce.600ms="body" maxlength="5000"></textarea>
                            <div class="et-phs">
                                <span class="et-phs-label"><i class="ti ti-braces"></i> Insert</span>
                                @foreach($placeholders as $name => $desc)
                                    <button type="button" class="et-ph" data-et-insert="{{ '{' . $name . '}' }}" title="{{ $desc }}">{{ '{' . $name . '}' }}</button>
                                @endforeach
                            </div>
                        </div>
                        @error('body')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @if($unknown)
                            <div class="et-note bad mt-2"><i class="ti ti-alert-circle"></i><span>Not a placeholder of this email: <b>{{ collect($unknown)->map(fn ($u) => '{' . $u . '}')->implode(', ') }}</b> — it will be sent as typed.</span></div>
                        @endif
                    </div>

                    {{-- Button --}}
                    <div class="et-field mb-0">
                        <div class="et-field-label"><label>Call-to-action button <span class="et-optional">optional</span></label></div>
                        <div class="row g-2">
                            <div class="col-md-5">
                                <div class="et-input-icon">
                                    <i class="ti ti-click"></i>
                                    <input type="text" class="form-control @error('buttonText') is-invalid @enderror" wire:model.debounce.600ms="buttonText" maxlength="60" placeholder="Button text, e.g. View my application" aria-label="Button text">
                                </div>
                                @error('buttonText')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-7">
                                <div class="et-input-icon">
                                    <i class="ti ti-link"></i>
                                    <input type="text" class="form-control @error('buttonUrl') is-invalid @enderror" wire:model.debounce.600ms="buttonUrl" maxlength="255" placeholder="Link, e.g. {portal_url}" aria-label="Button link">
                                </div>
                                @error('buttonUrl')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <div class="et-panel-foot">
                    <small class="text-muted"><i class="ti ti-shield-check"></i> Changes are recorded in the audit trail.</small>
                    <button type="submit" class="btn btn-primary px-4 ms-auto" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save"><i class="ti ti-device-floppy me-1"></i> Save template</span>
                        <span wire:loading wire:target="save"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                    </button>
                </div>
            </form>

            {{-- Institute sender details --}}
            @if($isInstituteScope)
                <form wire:submit.prevent="saveSettings" class="et-panel" wire:key="et-sender-{{ $scope }}">
                    <div class="et-panel-head">
                        <div class="min-w-0">
                            <div class="et-kicker"><i class="ti ti-id"></i> Sender details</div>
                            <h5 class="et-name">{{ $scopeLabel }}</h5>
                            <p class="et-desc">Used for every email to this institute's students.</p>
                        </div>
                    </div>
                    <div class="et-panel-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Sender name</label>
                                <input type="text" class="form-control @error('senderName') is-invalid @enderror" wire:model.defer="senderName" maxlength="100" placeholder="{{ $scopeLabel }}">
                                @error('senderName')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="et-hint">Shown as the sender; the address stays {{ $senderEmail ?: 'the SMTP2GO sender' }}.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reply-to address</label>
                                <input type="email" class="form-control @error('replyTo') is-invalid @enderror" wire:model.defer="replyTo" maxlength="150" placeholder="admissions@institute.com">
                                @error('replyTo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="et-hint">Where student replies go. Empty: the institute's email.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Signature</label>
                                <textarea class="form-control @error('signature') is-invalid @enderror" rows="3" wire:model.defer="signature" maxlength="1000" placeholder="Regards,&#10;Admissions Office"></textarea>
                                @error('signature')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="et-hint">Added under every email. Empty: “Regards, {{ $scopeLabel }}”.</div>
                            </div>
                        </div>
                    </div>
                    <div class="et-panel-foot">
                        <button type="submit" class="btn btn-outline-primary px-4 ms-auto" wire:loading.attr="disabled" wire:target="saveSettings">
                            <span wire:loading.remove wire:target="saveSettings"><i class="ti ti-device-floppy me-1"></i> Save sender details</span>
                            <span wire:loading wire:target="saveSettings"><span class="spinner-border spinner-border-sm me-1"></span> Saving…</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>

        {{-- Live preview --}}
        <div class="et-preview-col">
            <div class="et-panel et-preview-panel">
                <div class="et-panel-head is-compact">
                    <div class="min-w-0">
                        <h5 class="et-name mb-0"><i class="ti ti-eye me-1"></i> Preview</h5>
                        <p class="et-desc">Example values · updates as you type</p>
                    </div>
                    <span class="et-livebadge">
                        <span class="et-livebadge-in" wire:loading.remove wire:target="subject, body, buttonText, buttonUrl"><span class="et-livedot"></span> Live</span>
                        <span class="et-livebadge-in" wire:loading wire:target="subject, body, buttonText, buttonUrl"><span class="spinner-border"></span> Updating</span>
                    </span>
                </div>
                <div class="et-envelope">
                    <div class="et-avatar">{{ mb_strtoupper(mb_substr((string) $previewFrom, 0, 1)) ?: 'S' }}</div>
                    <div class="min-w-0">
                        <div class="et-env-subject">{{ $previewSubject }}</div>
                        <div class="et-env-line"><b>{{ $previewFrom }}</b> &lt;{{ $senderEmail ?: 'SMTP2GO_SENDER_EMAIL' }}&gt;</div>
                        @if($previewReplyTo)<div class="et-env-line">Reply-to: {{ $previewReplyTo }}</div>@endif
                    </div>
                </div>
                <div class="et-frame">
                    @if($previewHtml)
                        <iframe class="et-preview" srcdoc="{{ $previewHtml }}" title="Email preview" sandbox=""></iframe>
                    @else
                        <div class="p-4 text-muted small">The preview could not be built.</div>
                    @endif
                </div>
                <div class="et-test">
                    <div class="et-test-label"><i class="ti ti-send"></i> Send this preview as a test</div>
                    <form wire:submit.prevent="sendTest" class="et-test-form">
                        <input type="email" class="form-control form-control-sm @error('testEmail') is-invalid @enderror" wire:model.defer="testEmail" placeholder="name@example.com" aria-label="Test address">
                        <button type="submit" class="btn btn-sm btn-dark" wire:loading.attr="disabled" wire:target="sendTest">
                            <span wire:loading.remove wire:target="sendTest">Send test</span>
                            <span wire:loading wire:target="sendTest"><span class="spinner-border spinner-border-sm me-1"></span> Sending…</span>
                        </button>
                    </form>
                    @error('testEmail')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    @if($testResult)
                        <div class="et-note {{ $testResult['ok'] ? 'ok' : 'bad' }} mt-2"><i class="ti {{ $testResult['ok'] ? 'ti-circle-check' : 'ti-alert-circle' }}"></i><span>{{ $testResult['ok'] ? '' : 'Could not send: ' }}{{ $testResult['message'] }}</span></div>
                    @endif
                    <div class="et-hint">Sends what is in the form now, saved or not — even while sending is off.</div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .et-ui { --et-line: #E6E8EE; --et-ink: #0F172A; --et-muted: #64748B; --et-soft: #F8FAFC; --et-accent: #F26522; --et-navy: #1E2A4A; --et-radius: 14px; }
        .et-ui .min-w-0 { min-width: 0; }

        /* Header */
        .et-ui .et-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
        .et-ui .et-title { display: flex; align-items: center; gap: 10px; font-size: 22px; font-weight: 700; color: var(--et-ink); margin: 2px 0 4px; }
        .et-ui .et-title-icon { width: 38px; height: 38px; border-radius: 11px; display: inline-grid; place-items: center; background: linear-gradient(135deg, #F26522, #F59E0B); color: #fff; font-size: 20px; box-shadow: 0 6px 14px rgba(242, 101, 34, .25); }
        .et-ui .et-lead { margin: 0; color: var(--et-muted); font-size: 13.5px; }
        .et-ui .et-head-side { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .et-ui .et-scope { display: flex; align-items: center; gap: 4px; margin: 0; padding: 3px 3px 3px 12px; border: 1px solid var(--et-line); border-radius: 11px; background: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
        .et-ui .et-scope-label { font-size: 12px; font-weight: 600; color: var(--et-muted); white-space: nowrap; }
        .et-ui .et-scope .form-select { border: 0; min-width: 240px; box-shadow: none; font-weight: 600; color: var(--et-ink); }
        .et-ui .et-chip { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 999px; background: #fff; border: 1px solid var(--et-line); font-size: 13px; font-weight: 600; }

        /* Summary */
        .et-ui .et-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
        .et-ui .et-stat { display: flex; align-items: center; gap: 12px; padding: 12px 14px; background: #fff; border: 1px solid var(--et-line); border-radius: var(--et-radius); }
        .et-ui .et-stat b { display: block; font-size: 18px; line-height: 1.1; color: var(--et-ink); }
        .et-ui .et-stat small { color: var(--et-muted); font-size: 12px; }
        .et-ui .et-stat-icon { width: 38px; height: 38px; flex-shrink: 0; border-radius: 10px; display: grid; place-items: center; font-size: 19px; background: #EEF2FF; color: #4338CA; }
        .et-ui .et-stat-icon.is-orange { background: #FFF1E8; color: #C2410C; }
        .et-ui .et-stat-icon.is-red { background: #FEF2F2; color: #B91C1C; }
        .et-ui .et-stat-icon.is-green { background: #DCFCE7; color: #15803D; }
        .et-ui .et-stat-icon.is-grey { background: #F1F5F9; color: #64748B; }
        .et-ui .et-live.on { color: #15803D; } .et-ui .et-live.off { color: #64748B; }

        /* Notes */
        .et-ui .et-note { display: flex; gap: 10px; padding: 11px 14px; border-radius: 12px; font-size: 13px; }
        .et-ui .et-note i { font-size: 17px; flex-shrink: 0; margin-top: 1px; }
        .et-ui .et-note.warn { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
        .et-ui .et-note.ok { background: #F0FDF4; color: #166534; border: 1px solid #BBF7D0; }
        .et-ui .et-note.bad { background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; word-break: break-word; }

        /* Layout: list | editor | preview */
        .et-ui .et-layout { display: grid; grid-template-columns: 260px minmax(0, 1.1fr) minmax(0, 1fr); gap: 16px; align-items: start; }
        .et-ui .et-main { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
        .et-ui .et-panel { background: #fff; border: 1px solid var(--et-line); border-radius: var(--et-radius); overflow: hidden; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
        .et-ui .et-panel-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; padding: 16px 20px; border-bottom: 1px solid var(--et-line); }
        .et-ui .et-panel-head.is-compact { padding: 12px 16px; align-items: center; }
        .et-ui .et-panel-body { padding: 18px 20px; }
        .et-ui .et-panel-foot { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 12px 20px; background: var(--et-soft); border-top: 1px solid var(--et-line); }
        .et-ui .et-kicker { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; font-size: 11.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--et-accent); margin-bottom: 3px; }
        .et-ui .et-sep { color: #CBD5E1; margin: 0 2px; }
        .et-ui .et-name { font-size: 16.5px; font-weight: 700; color: var(--et-ink); margin: 0; }
        .et-ui .et-desc { margin: 2px 0 0; font-size: 12.5px; color: var(--et-muted); }

        /* Template list */
        .et-ui .et-side { position: sticky; top: 80px; min-width: 0; }
        .et-ui .et-list { padding: 8px; max-height: calc(100vh - 100px); overflow-y: auto; scrollbar-width: thin; }
        .et-ui .et-panel-title { padding: 8px 10px 2px; font-size: 14px; font-weight: 700; color: var(--et-ink); }
        /* Toggle menu: one collapsible box per group */
        .et-ui .et-menu-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 8px 8px 10px; }
        .et-ui .et-panel-title em { font-style: normal; margin-left: 4px; padding: 0 7px; border-radius: 999px; background: #F1F5F9; color: var(--et-muted); font-size: 11px; font-weight: 700; vertical-align: 2px; }
        .et-ui .et-menu-tools { display: inline-flex; gap: 2px; }
        .et-ui .et-menu-tools button { width: 28px; height: 28px; display: grid; place-items: center; border: 1px solid transparent; border-radius: 8px; background: transparent; color: #64748B; font-size: 15px; }
        .et-ui .et-menu-tools button:hover { background: var(--et-soft); border-color: var(--et-line); color: var(--et-ink); }
        .et-ui .et-menu-search { position: relative; margin: 0 2px 8px; }
        .et-ui .et-menu-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 15px; pointer-events: none; }
        .et-ui .et-menu-search input { width: 100%; height: 34px; padding: 0 10px 0 32px; border: 1px solid var(--et-line); border-radius: 10px; background: var(--et-soft); font-size: 13px; color: var(--et-ink); outline: 0; transition: border-color .15s, background .15s; }
        .et-ui .et-menu-search input:focus { background: #fff; border-color: #F8A77F; box-shadow: 0 0 0 3px rgba(242, 101, 34, .1); }
        .et-ui .et-group-box { margin: 0 2px 6px; border: 1px solid var(--et-line); border-radius: 12px; background: #fff; overflow: hidden; }
        .et-ui .et-group-box.has-active { border-color: #FED7C3; }
        .et-ui .et-group-head { display: flex; align-items: center; gap: 9px; padding: 9px 10px; cursor: pointer; list-style: none; user-select: none; transition: background .12s; }
        .et-ui .et-group-head::-webkit-details-marker { display: none; }
        .et-ui .et-group-head::marker { content: ""; }
        .et-ui .et-group-head:hover { background: var(--et-soft); }
        .et-ui .et-group-box[open] > .et-group-head { background: var(--et-soft); border-bottom: 1px solid var(--et-line); }
        .et-ui .et-group-ico { width: 28px; height: 28px; flex-shrink: 0; border-radius: 8px; display: grid; place-items: center; background: #EEF2FF; color: #4338CA; font-size: 15px; }
        .et-ui .et-group-box.has-active .et-group-ico { background: #FFF1E8; color: #C2410C; }
        .et-ui .et-group-name { flex: 1 1 auto; min-width: 0; font-size: 13px; font-weight: 700; color: var(--et-ink); line-height: 1.25; }
        .et-ui .et-group-count { min-width: 22px; padding: 0 6px; border-radius: 999px; background: #F1F5F9; color: #475569; font-size: 11px; font-weight: 700; text-align: center; line-height: 18px; }
        .et-ui .et-group-off { padding: 0 6px; border-radius: 999px; background: #FEE2E2; color: #B91C1C; font-size: 10.5px; font-weight: 700; line-height: 18px; white-space: nowrap; }
        .et-ui .et-group-chev { color: #94A3B8; font-size: 16px; transition: transform .18s; }
        .et-ui .et-group-box[open] .et-group-chev { transform: rotate(180deg); }
        .et-ui .et-group-items { padding: 4px; }
        .et-ui .et-menu-empty { padding: 14px 10px; text-align: center; font-size: 12.5px; color: #94A3B8; }
        .et-ui .et-item { width: 100%; display: flex; align-items: center; gap: 10px; padding: 8px 10px; margin-top: 2px; border: 1px solid transparent; border-radius: 10px; background: transparent; text-align: left; color: var(--et-ink); transition: background .12s, border-color .12s; }
        .et-ui .et-item:hover { background: var(--et-soft); }
        .et-ui .et-item.is-active { background: #FFF6F0; border-color: #FED7C3; }
        .et-ui .et-item.is-active .et-item-name { color: #C2410C; }
        .et-ui .et-item-name { display: block; font-size: 13.5px; font-weight: 600; line-height: 1.3; }
        .et-ui .et-item-sub { display: flex; gap: 4px; margin-top: 3px; }
        .et-ui .et-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
        .et-ui .et-dot.on { background: #22C55E; box-shadow: 0 0 0 3px #DCFCE7; } .et-ui .et-dot.off { background: #CBD5E1; box-shadow: 0 0 0 3px #F1F5F9; }
        .et-ui .et-tag { display: inline-block; padding: 1px 7px; border-radius: 999px; font-size: 10.5px; font-weight: 600; line-height: 16px; }
        .et-ui .et-tag.def { background: #F1F5F9; color: #475569; } .et-ui .et-tag.group { background: #EEF2FF; color: #4338CA; }
        .et-ui .et-tag.inst { background: #FFEDD5; color: #C2410C; } .et-ui .et-tag.off { background: #FEE2E2; color: #B91C1C; }
        .et-ui .et-legend { display: grid; gap: 5px; margin: 10px 2px 2px; padding: 10px 8px 4px; border-top: 1px dashed var(--et-line); font-size: 11.5px; color: var(--et-muted); }
        .et-ui .et-legend .et-tag { min-width: 58px; text-align: center; margin-right: 4px; }
        .et-ui .et-picker { display: none; }

        /* Send switch */
        .et-ui .et-toggle { display: flex; align-items: center; gap: 10px; margin: 0; padding: 8px 12px; border: 1px solid var(--et-line); border-radius: 12px; background: var(--et-soft); cursor: pointer; }
        .et-ui .et-toggle b { display: block; font-size: 13px; color: var(--et-ink); } .et-ui .et-toggle small { display: block; font-size: 11px; color: var(--et-muted); }
        .et-ui .et-switch { position: relative; width: 40px; height: 22px; flex-shrink: 0; margin: 0; }
        .et-ui .et-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
        .et-ui .et-switch span { position: absolute; inset: 0; border-radius: 999px; background: #CBD5E1; transition: background .15s; }
        .et-ui .et-switch span::after { content: ""; position: absolute; left: 3px; top: 3px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: transform .15s; box-shadow: 0 1px 2px rgba(0,0,0,.2); }
        .et-ui .et-switch input:checked + span { background: #22C55E; }
        .et-ui .et-switch input:checked + span::after { transform: translateX(18px); }
        .et-ui .et-switch input:focus-visible + span { outline: 2px solid #93C5FD; outline-offset: 2px; }

        /* Version strip */
        .et-ui .et-version { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 20px; background: #F8FAFC; border-bottom: 1px solid var(--et-line); font-size: 12.5px; color: #475569; }
        .et-ui .et-version > i { font-size: 16px; color: #94A3B8; }
        .et-ui .et-version.is-own { background: #FFF8F3; color: #7C2D12; } .et-ui .et-version.is-own > i { color: var(--et-accent); }
        .et-ui .et-btn-ghost { background: #fff; border: 1px solid var(--et-line); color: #334155; }
        .et-ui .et-btn-ghost:hover { border-color: #CBD5E1; background: #fff; }
        .et-ui .et-confirm { display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 12.5px; color: #991B1B; font-weight: 600; }

        /* Fields */
        .et-ui .et-field { margin-bottom: 18px; }
        .et-ui .et-field-label { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; margin-bottom: 6px; }
        .et-ui .et-field-label label { font-size: 13px; font-weight: 600; color: var(--et-ink); margin: 0; }
        .et-ui .et-count { font-size: 11px; color: #94A3B8; font-variant-numeric: tabular-nums; }
        .et-ui .et-optional { margin-left: 4px; padding: 1px 6px; border-radius: 6px; background: #F1F5F9; color: var(--et-muted); font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
        .et-ui .et-hint { font-size: 12px; color: var(--et-muted); margin-top: 6px; }
        .et-ui .et-input-icon { position: relative; }
        .et-ui .et-input-icon > i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #94A3B8; font-size: 16px; pointer-events: none; }
        .et-ui .et-input-icon .form-control { padding-left: 34px; }

        /* Message editor */
        .et-ui .et-editor { border: 1px solid #D7DCE5; border-radius: 12px; overflow: hidden; background: #fff; transition: border-color .15s, box-shadow .15s; }
        .et-ui .et-editor:focus-within { border-color: #F8A77F; box-shadow: 0 0 0 3px rgba(242, 101, 34, .12); }
        .et-ui .et-editor.is-invalid { border-color: #F87171; }
        .et-ui .et-toolbar { display: flex; align-items: center; gap: 4px; padding: 6px 8px; background: var(--et-soft); border-bottom: 1px solid var(--et-line); flex-wrap: wrap; }
        .et-ui .et-tool { width: 30px; height: 28px; display: grid; place-items: center; border: 1px solid transparent; border-radius: 7px; background: transparent; color: #334155; font-size: 16px; }
        .et-ui .et-tool:hover { background: #fff; border-color: var(--et-line); }
        .et-ui .et-toolbar-hint { margin-left: auto; font-size: 11.5px; color: #94A3B8; padding-right: 4px; }
        .et-ui .et-toolbar-hint code { font-size: 11px; color: #475569; background: #EEF2F7; padding: 0 4px; border-radius: 4px; }
        .et-ui .et-body { display: block; width: 100%; border: 0; outline: 0; resize: vertical; padding: 12px 14px; min-height: 240px; font-family: 'IBM Plex Mono', ui-monospace, Consolas, monospace; font-size: 13px; line-height: 21px; color: var(--et-ink); background: #fff; }
        .et-ui .et-phs { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 9px 10px; border-top: 1px solid var(--et-line); background: var(--et-soft); }
        .et-ui .et-phs-label { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 700; color: var(--et-muted); margin-right: 2px; }
        .et-ui .et-ph { border: 1px solid #DDE3FB; background: #fff; color: #4338CA; border-radius: 7px; padding: 2px 8px; font-size: 11.5px; font-family: ui-monospace, Consolas, monospace; transition: background .12s, border-color .12s; }
        .et-ui .et-ph:hover { background: #EEF2FF; border-color: #A5B4FC; }

        /* Preview */
        .et-ui .et-preview-col { position: sticky; top: 80px; min-width: 0; }
        .et-ui .et-livebadge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; background: #F0FDF4; color: #15803D; font-size: 11.5px; font-weight: 700; }
        .et-ui .et-livedot { display: inline-block; width: 7px; height: 7px; margin-right: 4px; border-radius: 50%; background: #22C55E; vertical-align: 1px; }
        .et-ui .et-livebadge .spinner-border { margin-right: 4px; vertical-align: -1px; }
        .et-ui .et-livebadge .spinner-border { width: 11px; height: 11px; border-width: 2px; }
        .et-ui .et-envelope { display: flex; gap: 12px; align-items: flex-start; padding: 12px 16px; border-bottom: 1px solid var(--et-line); }
        .et-ui .et-avatar { width: 36px; height: 36px; flex-shrink: 0; border-radius: 50%; display: grid; place-items: center; background: var(--et-navy); color: #fff; font-weight: 700; font-size: 15px; }
        .et-ui .et-env-subject { font-size: 14px; font-weight: 700; color: var(--et-ink); word-break: break-word; }
        .et-ui .et-env-line { font-size: 12px; color: var(--et-muted); word-break: break-word; }
        .et-ui .et-env-line b { color: #334155; }
        .et-ui .et-frame { background: #EEF0F6; }
        .et-ui .et-preview { display: block; width: 100%; height: 560px; border: 0; background: #EEF0F6; }
        .et-ui .et-test { padding: 12px 16px 14px; border-top: 1px solid var(--et-line); background: var(--et-soft); }
        .et-ui .et-test-label { font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
        .et-ui .et-test-form { display: flex; gap: 8px; }
        .et-ui .et-test-form .form-control { flex: 1 1 auto; min-width: 0; }
        .et-ui .et-test-form .btn { flex-shrink: 0; }

        @media (max-width: 1399.98px) {
            .et-ui .et-layout { grid-template-columns: 260px minmax(0, 1fr); }
            .et-ui .et-preview-col { grid-column: 2; position: static; }
        }
        @media (max-width: 991.98px) {
            .et-ui .et-layout { grid-template-columns: minmax(0, 1fr); }
            .et-ui .et-preview-col { grid-column: auto; }
            .et-ui .et-side { position: static; }
            .et-ui .et-list { display: none; }
            .et-ui .et-picker { display: block; background: #fff; border: 1px solid var(--et-line); border-radius: var(--et-radius); padding: 12px 14px; }
            .et-ui .et-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .et-ui .et-scope { width: 100%; } .et-ui .et-scope .form-select { min-width: 0; flex: 1; }
            .et-ui .et-head-side { width: 100%; }
            .et-ui .et-preview { height: 520px; }
        }
        @media (max-width: 575.98px) {
            .et-ui .et-panel-head, .et-ui .et-panel-body, .et-ui .et-panel-foot, .et-ui .et-version { padding-left: 14px; padding-right: 14px; }
            .et-ui .et-toggle { width: 100%; }
            .et-ui .et-version > i { display: none; }
            .et-ui .et-toolbar-hint { display: none; }
            .et-ui .et-stat { padding: 10px; gap: 10px; }
        }
    </style>

    <script>
        // Template menu: expand / collapse all groups, and find a template by name.
        (function () {
            if (window.__etMenu) return;
            window.__etMenu = true;
            function filterMenu() {
                var input = document.getElementById('et-menu-filter'), menu = document.getElementById('et-menu');
                if (!input || !menu) return;
                var term = input.value.trim().toLowerCase(), any = false;
                menu.querySelectorAll('.et-group-box').forEach(function (box) {
                    var hits = 0;
                    box.querySelectorAll('.et-item').forEach(function (item) {
                        var show = !term || (item.dataset.etName || '').indexOf(term) !== -1;
                        item.hidden = !show; if (show) hits++;
                    });
                    box.hidden = hits === 0;
                    if (term && hits) box.open = true;
                    if (hits) any = true;
                });
                var empty = document.getElementById('et-menu-empty');
                if (empty) empty.hidden = any;
            }
            document.addEventListener('input', function (e) { if (e.target.id === 'et-menu-filter') filterMenu(); });
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-et-groups]');
                if (!btn) return;
                document.querySelectorAll('#et-menu .et-group-box').forEach(function (box) { box.open = btn.dataset.etGroups === 'open'; });
            });
            document.addEventListener('livewire:load', function () {
                if (window.Livewire && Livewire.hook) Livewire.hook('message.processed', filterMenu);
            });
        })();

        // Message editor helpers: insert a placeholder, wrap the selection in **bold**, or start the line with "- ".
        (function () {
            if (window.__etChips) return;
            window.__etChips = true;
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-et-insert], [data-et-wrap], [data-et-line]');
                if (!btn) return;
                var wrap = btn.closest('[data-et-target]');
                var box = wrap && document.getElementById(wrap.dataset.etTarget);
                if (!box) return;
                var v = box.value, start = box.selectionStart ?? v.length, end = box.selectionEnd ?? start, text, caretStart, caretEnd;
                if (btn.dataset.etWrap) {
                    var mark = btn.dataset.etWrap, picked = v.slice(start, end) || 'text';
                    text = mark + picked + mark;
                    caretStart = start + mark.length; caretEnd = caretStart + picked.length;
                } else if (btn.dataset.etLine) {
                    var lineStart = v.lastIndexOf('\n', start - 1) + 1;
                    start = end = lineStart; text = btn.dataset.etLine;
                    caretStart = caretEnd = lineStart + text.length;
                } else {
                    text = btn.dataset.etInsert;
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
