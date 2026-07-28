<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeadsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $isCarib = $user->region === 'carib';

        $summary = $isCarib
            ? ['leads_created' => 32, 'leads_assigned' => 49, 'mqls' => 60, 'sqls' => 80]
            : ['leads_assigned' => 49, 'mqls' => 60, 'sqls' => 80];

        return Inertia::render('leads', [
            'variant' => $isCarib ? 'carib' : 'latam',
            'summary' => $summary,
            'leads' => $this->mockLeads(),
        ]);
    }

    private function mockLeads(): array
    {
        return [
            ['name' => 'Patricia Gomez',    'created_date' => '2026-01-10', 'owner' => 'Carlos Rivera'],
            ['name' => 'Andrés Martínez',   'created_date' => '2026-01-12', 'owner' => 'Sofia López'],
            ['name' => 'Lucia Fernández',   'created_date' => '2026-01-15', 'owner' => 'Juan Pérez'],
            ['name' => 'Roberto Silva',     'created_date' => '2026-01-18', 'owner' => 'Ana Torres'],
            ['name' => 'María García',      'created_date' => '2026-01-20', 'owner' => 'Carlos Rivera'],
            ['name' => 'Diego Ramírez',     'created_date' => '2026-02-01', 'owner' => 'Sofia López'],
            ['name' => 'Carmen Ruiz',       'created_date' => '2026-02-05', 'owner' => 'Juan Pérez'],
            ['name' => 'Felipe Castro',     'created_date' => '2026-02-08', 'owner' => 'Ana Torres'],
            ['name' => 'Valentina Mora',    'created_date' => '2026-02-12', 'owner' => 'Carlos Rivera'],
            ['name' => 'Santiago Herrera',  'created_date' => '2026-02-15', 'owner' => 'Sofia López'],
            ['name' => 'Isabella Vargas',   'created_date' => '2026-02-20', 'owner' => 'Juan Pérez'],
            ['name' => 'Tomás Díaz',        'created_date' => '2026-03-01', 'owner' => 'Ana Torres'],
            ['name' => 'Camila Jiménez',    'created_date' => '2026-03-05', 'owner' => 'Carlos Rivera'],
            ['name' => 'Nicolás Reyes',     'created_date' => '2026-03-10', 'owner' => 'Sofia López'],
        ];
    }
}
