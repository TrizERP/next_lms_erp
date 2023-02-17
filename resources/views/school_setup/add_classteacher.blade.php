@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')
<style>
.title{
    font-weight:200;
}
</style>
<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
                <h4 class="page-title">
                @if(!isset($data['data']))
                Add Class Teacher
                @else
                Edit Class Teacher
                @endif
                </h4>
            </div>            
        </div>
        <div class="row" style=" margin-top: 25px;">
        <div class="white-box">   
            <div class="panel-body">
                @if ($message = Session::get('data'))
                <div class="alert alert-success alert-block">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    <strong>{{ $message['message'] }}</strong>
                </div>
                @endif
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <form action="{{ route('ajax_getClasswiseTimetable') }}" enctype="multipart/form-data" method="post">
                        <div class="col-md-10 form-group">                                                        
                            {{ App\Helpers\SearchChain('4','single','grade,std,div',$data['academic_section_id'],$data['standard_id'],$data['division_id']) }}
                        </div>                                                
                        <div class="col-md-2 form-group">                                                        
                            <br>
                            <input type="submit" name="submit" value="Submit" class="btn btn-success" >                                                    
                        </div>
                    </form>                    
                </div>
                <div class="col-lg-12 col-sm-12 col-xs-12">
                    <div class="col-md-12 form-group"> 
                        <!-- <div class="panel"> -->
                            <div class="table-responsive">
                                @if( isset($data['HTML']) )
                                    @php echo $data['HTML'] @endphp
                                @endif
                            </div> 
                        <!-- </div-.>  -->
                    </div>                    
                </div>                    
                @if (count($errors) > 0)
                <div class="alert alert-danger">
                    <strong>Whoops!</strong> There were some problems with your input.<br><br>
                    <ul>
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>
            </div>
        </div>
    </div>
</div>

@include('includes.footerJs')
@include('includes.footer')
