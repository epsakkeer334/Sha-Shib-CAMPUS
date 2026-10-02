<?php

namespace App\Http\Livewire\Portal;

use Livewire\Component;

/**
 * Admissions home (design: "Website · Admissions home").
 */
class HomePage extends Component
{
    public function render()
    {
        return view('portal.home')->layout('layouts.portal', ['title' => 'Apply for admission']);
    }
}
