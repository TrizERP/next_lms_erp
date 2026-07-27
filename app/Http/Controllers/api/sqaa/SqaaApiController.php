<?php

namespace App\Http\Controllers\api\sqaa;

use App\Http\Controllers\Controller;
use App\Models\sqaa\sqaa_document;
use App\Models\sqaa\sqaa_mark;
use App\Models\sqaa\sqaa_master;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SqaaApiController extends Controller
{
    public function levels(Request $request)
    {
        $validated = $request->validate([
            'level' => ['nullable', 'integer', 'between:1,4'],
            'parent_id' => ['nullable', 'integer', 'min:0'],
        ]);

        $level = (int) ($validated['level'] ?? 1);
        $parentId = (int) ($validated['parent_id'] ?? 0);

        $levels = sqaa_master::query()
            ->where('level', $level)
            ->when($level > 1, function ($query) use ($parentId) {
                $query->where('parent_id', $parentId);
            })
            ->when($level === 1, function ($query) {
                $query->where('parent_id', 0);
            })
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $levels]);
    }

    public function entry(Request $request, $menuId)
    {
        $subInstituteId = (int) session()->get('sub_institute_id');
        $menu = sqaa_master::findOrFail($menuId);

        $documents = DB::table('sqaa_documant_master as master')
            ->leftJoin('sqaa_documents as document', function ($join) use ($subInstituteId) {
                $join->on('document.document_id', '=', 'master.id')
                    ->where('document.sub_institute_id', '=', $subInstituteId);
            })
            ->where('master.menu_id', $menu->id)
            ->orderBy('master.id')
            ->select([
                'master.id as document_id',
                'master.menu_id',
                'master.title',
                'document.id as entry_id',
                'document.reasons',
                'document.availability',
                'document.file',
            ])
            ->get();

        $mark = sqaa_mark::where([
            'menu_id' => $menu->id,
            'sub_institute_id' => $subInstituteId,
        ])->latest('id')->first();

        return response()->json([
            'data' => [
                'menu' => $menu,
                'mark' => $mark ? (int) $mark->mark : null,
                'documents' => $documents,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'menu_id' => ['required', 'integer', Rule::exists('sqaa_master', 'id')],
            'mark' => ['required', 'integer', 'between:0,4'],
            'documents' => ['required', 'array', 'min:1'],
            'documents.*.document_id' => ['required', 'integer', Rule::exists('sqaa_documant_master', 'id')],
            'documents.*.title' => ['nullable', 'string'],
            'documents.*.availability' => ['required', Rule::in(['yes', 'no', 'inprocess'])],
            'documents.*.reasons' => ['nullable', 'string'],
            'documents.*.existing_file' => ['nullable', 'string', 'max:255'],
            'documents.*.file' => ['nullable', 'file', 'mimes:pdf,xlsx,doc,docx'],
        ]);

        $subInstituteId = (int) session()->get('sub_institute_id');
        $userId = (int) session()->get('user_id');
        $menuId = (int) $validated['menu_id'];
        $documentIds = collect($validated['documents'])
            ->pluck('document_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        $validDocumentIds = DB::table('sqaa_documant_master')
            ->where('menu_id', $menuId)
            ->whereIn('id', $documentIds)
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->all();

        if (count(array_unique($documentIds)) !== count($validDocumentIds)) {
            return response()->json([
                'message' => 'One or more documents do not belong to the selected SQAA standard.',
                'errors' => ['documents' => ['Invalid SQAA document selection.']],
            ], 422);
        }

        $uploadedFiles = [];
        try {
            $result = DB::transaction(function () use (
                $request,
                $validated,
                $subInstituteId,
                $userId,
                $menuId,
                &$uploadedFiles
            ) {
                $mark = sqaa_mark::updateOrCreate(
                    [
                        'menu_id' => $menuId,
                        'sub_institute_id' => $subInstituteId,
                    ],
                    [
                        'mark' => (int) $validated['mark'],
                        'created_by' => $userId,
                        'updated_at' => now(),
                    ]
                );

                $savedDocuments = [];
                foreach ($validated['documents'] as $index => $documentInput) {
                    $documentId = (int) $documentInput['document_id'];
                    $existing = sqaa_document::where([
                        'menu_id' => $menuId,
                        'document_id' => $documentId,
                        'sub_institute_id' => $subInstituteId,
                    ])->first();

                    $filename = $existing ? $existing->file : null;
                    $availability = $documentInput['availability'];
                    $file = $request->file("documents.$index.file");

                    if ($availability === 'yes' && $file && $file->isValid()) {
                        $filename = $documentId . '_' . time() . '_' . $file->getClientOriginalName();
                        Storage::disk('digitalocean')->putFileAs('public/sqaa/', $file, $filename, 'public');
                        $uploadedFiles[] = $filename;

                        if ($existing && $existing->file && $existing->file !== $filename) {
                            Storage::disk('digitalocean')->delete('public/sqaa/' . $existing->file);
                        }
                    } elseif ($availability !== 'yes' && $filename) {
                        Storage::disk('digitalocean')->delete('public/sqaa/' . $filename);
                        $filename = null;
                    }

                    $document = sqaa_document::updateOrCreate(
                        [
                            'menu_id' => $menuId,
                            'document_id' => $documentId,
                            'sub_institute_id' => $subInstituteId,
                        ],
                        [
                            'title' => $documentInput['title'] ?? null,
                            'reasons' => $documentInput['reasons'] ?? null,
                            'availability' => $availability,
                            'file' => $filename,
                            'created_by' => $userId,
                            'updated_at' => now(),
                        ]
                    );
                    $savedDocuments[] = $document;
                }

                return ['mark' => $mark, 'documents' => $savedDocuments];
            });
        } catch (\Throwable $exception) {
            foreach ($uploadedFiles as $filename) {
                Storage::disk('digitalocean')->delete('public/sqaa/' . $filename);
            }
            throw $exception;
        }

        return response()->json([
            'status' => 1,
            'message' => 'SQAA entry saved successfully.',
            'data' => $result,
        ]);
    }

    public function documentReport(Request $request)
    {
        $validated = $request->validate([
            'availability' => ['nullable', Rule::in(['yes', 'no', 'inprocess', 'all'])],
            'menu_id' => ['nullable', 'integer', Rule::exists('sqaa_master', 'id')],
        ]);

        $subInstituteId = (int) session()->get('sub_institute_id');
        $availability = $validated['availability'] ?? 'yes';
        $menuId = (int) ($validated['menu_id'] ?? 0);

        $documents = DB::table('sqaa_documents as document')
            ->join('sqaa_documant_master as master', 'master.id', '=', 'document.document_id')
            ->join('sqaa_master as menu', 'menu.id', '=', 'master.menu_id')
            ->where('document.sub_institute_id', $subInstituteId)
            ->when($availability !== 'all', function ($query) use ($availability) {
                $query->where('document.availability', $availability);
            })
            ->when($menuId !== 0, function ($query) use ($menuId) {
                $query->where('document.menu_id', $menuId);
            })
            ->orderBy('menu.sort_order')
            ->select([
                'document.id',
                'document.menu_id',
                'document.document_id',
                'document.title',
                'document.reasons',
                'document.availability',
                'document.file',
                'menu.title as menu_title',
            ])
            ->get();

        return response()->json(['data' => $documents]);
    }
}
