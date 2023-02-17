<!DOCTYPE html>
  @include('includes.headcss')
  {{-- Quill Editior --}}
  <link href="{!! url('css/quill.snow.css') !!}" rel="stylesheet">
  {{-- TinyMCE Editior --}}
  <script src="{!! url('js/tinymce.min.js') !!}"></script>
  <style>
      #page-wrapper {
          padding: 5rem 0;
      }
  </style>
  {{-- @include('includes.header') --}}
  {{-- @include('includes.sideNavigation') --}}

  <div id="page-wrapper">
      {!! $html !!}
  </div>

  @include('includes.footerJs')
  <script src="{!! url('js/quill.js') !!}"></script>
  
    {{-- TinyMCE Editior Script --}}
    <script type="text/javascript">
        tinymce.init({
            selector: 'textarea.tinymce',
            promotion: false
        });
    </script>

    {{-- Quill Editior Script --}}
    <script>
        $('form').on('submit', function(){
            tinyMCE.triggerSave();
        })
    </script>

    <script>
        $( document ).ready(function() {  
            var std = $("#standard").val(); 
            if ( std != '' ) {
                setTimeout(() => {
                    $("#standard").trigger("change");     
                }, 500);                
            }

            /* Get Suject List */
            $("#standard").change(function(){
                
                var std_id = $("#standard").val();
                $("input[name='standard']").val(std_id);
                $('input[name="subject"]').val('');

                var path = "{{ route('ajax_LMS_StandardwiseSubject') }}";
                $('#subject').find('option').remove().end().append('<option value="">Select Subject</option>').val('');
                $.ajax({
                    url: path,
                    data:'std_id='+std_id, 
                    success: function(result){
                        for(var i=0;i < result.length;i++){
                            $("#subject").append($("<option></option>").val(result[i]['subject_id']).html(result[i]['display_name']));
                        }

                        setTimeout(() => {
                            var get_subject_value = $("#subject").attr("data-value");
                            if ( get_subject_value != '' ) {
                                $('#subject').val(get_subject_value);
                                $('input[name="subject"]').val(get_subject_value);
                                $("#subject").attr("data-value", '');

                                $("#subject").trigger("change");
                            }
                        }, 100);
                    } 
                });
            });

            /* Get Chapter List */
            $("#subject").change(function(){
                var sub_id = $("#subject").val();
                $("input[name='subject']").val(sub_id);
                
                var std_id = $("#standard").val();
                var sub_id = $("#subject").val();
                var path = "{{ route('ajax_LMS_SubjectwiseChapterForBooklist') }}";
                $('#chapters').find('option').remove().end().append('<option value="">Select Chapter</option>').val('');
                $.ajax({
                    url: path,
                    data:'sub_id='+sub_id+'&std_id='+std_id, 
                    success: function(result){
                        for(var i=0;i < result.length;i++){
                            $("#chapters").append($("<option></option>").val(result[i]['id']).html(result[i]['chapter_name']));
                        }

                        setTimeout(() => {
                            var get_subject_value = $("#chapters").attr("data-value");
                            if ( get_subject_value != '' ) {
                                $('#chapters').val(get_subject_value);
                                $('input[name="chapter"]').val(get_subject_value);
                                $("#chapters").attr("data-value", '');
                            }
                        }, 100);
                    }
                });
                $("#title").val($("#subject option:selected").text());
            })

            $("#chapters").change(function(){
                var sub_id = $("#chapters").val();
                $("input[name='chapter']").val(sub_id);
            });
        });


        $('form').submit(function(){
            var chapter = $('#chapters').val();
            $('input[name="chapter"').val(chapter);
        });
    </script>
  @include('includes.footer')
</html>
