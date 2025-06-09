<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Blog</title>
    <script src="https://cdn.ckeditor.com/4.16.0/standard/ckeditor.js"></script>
    <style>
    body {
        background-color:rgb(255, 255, 255);
        color: #111;
        font-family: Arial, sans-serif;
        padding: 40px;
    }

    h1 {
        color:rgb(99, 16, 233);
    }

    form {
        max-width: 700px;
        margin: auto;
        background-color:rgb(207, 187, 243);
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 0 10px #000;
    }

    label {
        display: block;
        margin-top: 15px;
        font-weight: bold;
    }

    input[type="text"],
    select,
    textarea {
        width: 100%;
        max-width: 100%;
        box-sizing: border-box;
        padding: 10px;
        margin-top: 5px;
        border: none;
        border-radius: 5px;
        background-color: #F9FAFB;
        color: #111;
        font-size: 16px;
    }

    input[type="file"] {
        margin-top: 5px;
        background-color: #fff;
        color: #000;
        border-radius: 5px;
        padding: 10px;
    }

    /* CKEditor container override */
    .cke_editor_editor {
        width: 100% !important;
    }

    button {
        margin-top: 20px;
        padding: 12px 20px;
        background-color:rgb(97, 33, 199);
        color: white;
        border: none;
        border-radius: 5px;
        font-weight: bold;
        cursor: pointer;
        width: 100%;
    }

    button:hover {
        background-color:rgb(155, 96, 250);
    }

    .error {
        color: #F87171;
    }
</style>
</head>
<body>
    <h1 style="text-align:center;">Create Blog Post</h1>

    @if ($errors->any())
        <div class="error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('blogs.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <label>Type:</label>
        <select name="type">
            <option>Select Type</option>
            <option value="blog" {{ old('type') == 'blog' ? 'selected' : '' }}>Blog</option>
            <option value="case study" {{ old('type') == 'case study' ? 'selected' : '' }}>Case Study</option>
        </select>

        <label>Author:</label>
        <input type="text" name="author" placeholder="Author" value="{{ old('author') }}">

        <label>Image:</label>
        <input type="file" name="image">

        <label>Title:</label>
        <input type="text" name="title" placeholder="Title" id="title" value="{{ old('title') }}">

        <label>Description:</label>
        <textarea name="description" id="editor">{{ old('description') }}</textarea>

        <label>Slug:</label>
        <input type="text" name="slug" placeholder="Slug" id="slug" value="{{ old('slug') }}">

        <label>Meta Title:</label>
        <input type="text" name="meta_title" placeholder="Meta_Title" id="meta_title" value="{{ old('meta_title') }}">

        <label>Meta Description:</label>
        <textarea name="meta_description" placeholder="Meta_Descripton" id="meta_description">{{ old('meta_description') }}</textarea>

        <label>Meta Keywords:</label>
        <input type="text" name="meta_keyword" placeholder="Meta_Keywords" id="meta_keyword" value="{{ old('meta_keyword') }}">

        <label>Status:</label>
        <select name="status">
            <option>Status:-</option>
            <option value="published" {{ old('status') == 'published' ? 'selected' : '' }}>Published</option>
            <option value="draft" {{ old('status') == 'draft' ? 'selected' : '' }}>Draft</option>
            <option value="archieved" {{ old('status') == 'archieved' ? 'selected' : '' }}>Archieved</option>
        </select>

        <button type="submit">Save</button>
    </form>

   <script>
            CKEDITOR.replace('editor');

            // Auto-fill based on title
            document.getElementById("title").addEventListener("input", function() {
                const title = this.value.trim();

                const slug = title
                    .toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, "")
                    .replace(/\s+/g, "-")
                    .replace(/-+/g, "-");

                document.getElementById("slug").value = slug;
                document.getElementById("meta_title").value = title;
                document.getElementById("meta_description").value = `Learn more about ${title.toLowerCase()} in this blog post.`;
                document.getElementById("meta_keyword").value = title.toLowerCase().split(" ").join(", ");
            });

            // Validate before submitting
            document.querySelector("form").addEventListener("submit", function(e) {
                const requiredFields = [
                    "type",
                    "author",
                    "title",
                    "slug",
                    "meta_title",
                    "meta_description",
                    "meta_keyword",
                    "status"
                ];

                let isValid = true;
                requiredFields.forEach(function(fieldId) {
                    const field = document.getElementsByName(fieldId)[0];
                    if (!field.value.trim()) {
                        field.style.border = "2px solid red";
                        isValid = false;
                    } else {
                        field.style.border = "none";
                    }
                });

                const descriptionData = CKEDITOR.instances.editor.getData().trim();
                if (!descriptionData) {
                    alert("Please fill out the description field.");
                    isValid = false;
                }

                if (!isValid) {
                    e.preventDefault();
                    alert("Please fill in all fields before submitting the form.");
                }
            });
    </script>
</body>
</html>
