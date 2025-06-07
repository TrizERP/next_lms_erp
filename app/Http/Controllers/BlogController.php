<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BlogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $blogs = Blog::latest()->get();
        return view('blogs.index', compact('blogs'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function create()
    {
        return view('blogs.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:blog,case study',
            'author' => 'required|string|max:255',
            'image' => 'nullable|image|max:2048',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'slug' => 'required|string|unique:blogs,slug',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keyword' => 'nullable|string',
            'status' => 'required|in:published,draft,archieved',
        ]);

        $data = $request->all();

        if($request->hasFile('image')){
            $file = $request->file('image');
            $data['image'] = $filename = date('YmdHis').'_'.$file->getClientOriginalName();
            Storage::disk('digitalocean')->putFileAs('public/blogs/', $file, $filename, 'public');
            // $data['image'] = $request->file('image')->store('blog_images', 'public');   
        }
        // echo "<pre>";print_r($data);exit;

        Blog::create($data);
        return redirect()->route('blogs.index')->with('success', 'Blog created successfully.');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Blog  $blog
     * @return \Illuminate\Http\Response
     */
    public function show(Blog $blog)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Blog  $blog
     * @return \Illuminate\Http\Response
     */
    public function edit(Blog $blog)
    {
        return view('blogs.edit', compact('blog'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Blog  $blog
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Blog $blog)
    {
        $request->validate([
            'type'=> 'required|in:blog, case study',
            'author' => 'required',
            'title' => 'required',
            'description' => 'required',
            'slug' => 'required|unique:blogs,slug,' .$blog->id,
            'status' => 'required|in:published,draft,archieved',
        ]);

        $data = $request->all();

        if($request->hasFile('image')){
             $file = $request->file('image');
            $data['image'] = $filename = date('YmdHis').'_'.$file->getClientOriginalName();
            Storage::disk('digitalocean')->putFileAs('public/blogs/', $file, $filename, 'public');
            // $data['image'] = $request->file('image')->store('blog_images', 'public');   
        }

        $blog->update($data);
        return redirect()->route('blogs.index')->with('success', 'Blog Updated Successfully.');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Blog  $blog
     * @return \Illuminate\Http\Response
     */
    public function destroy(Blog $blog)
    {
        $blog->delete();
        return redirect()->route('blogs.index')->with('success', 'Blog Deleted Successfully.');
    }
}
