@include('../includes.headcss')
<link rel="stylesheet" href="../../../plugins/bower_components/dropify/dist/css/dropify.min.css">
@include('../includes.header')
@include('../includes.sideNavigation')


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="row bg-title">            
            <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">                
                <h4 class="page-title">Fees Breakoff</h4>            
            </div>                    
        </div>

        <div class="row">
            <div class="white-box">
                <form action="{{ route('update_fees_breackoff.store') }}" enctype="multipart/form-data" method="post">
                    <input type="hidden" value="insert" name="action">
                    @csrf
                    <input type="hidden" value="insert" name="action">
                    <div class="panel-body">
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                <table id="example" class="table table-striped">
                                    <tr style="border: 1px solid #000;">
                                        <td style="text-align: center;font-weight: inherit;border: 1px;">New Student</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <table border="1">
                                                <tr>
                                                    <th style="text-align: center;font-weight: inherit;">
                                                    {{ App\Helpers\get_string('studentquota','request')}}
                                                    </th>
                                                    @foreach ($data['data']['title_arr'] as $id => $val) 
                                                        <th style="text-align: center;font-weight: inherit;">
                                                            {{ $val }}
                                                        </th>
                                                    @endforeach
                                                    <th style="text-align: center;font-weight: inherit;">
                                                        Total
                                                    </th>
                                                </tr>
                                              @foreach ($data['data']['quota_arr'] as $quota_id => $quota_val)
                                              @php 
                                                    $total = 0;
                                                @endphp
                                                    <tr>
                                                        <td style="text-align: center;font-weight: inherit;">
                                                           {{ $quota_val}}
                                                        </td>
                                                        
                                                        @foreach ($data['data']['title_arr'] as $id => $val) 
                                                        @php
                                                            $amount_val = 0;
                                                            if (isset($data['data']['bk_arr']['new'][$quota_id][$id])) {
                                                                $amount_val = $data['data']['bk_arr']['new'][$quota_id][$id];
                                                                $total += $amount_val;
                                                            }
                                                        @endphp
                                                            <td style="text-align: center;font-weight: inherit;">
                                                                <input type="text" class="form-control" value="{{$amount_val}}" name="NewValues[{{$quota_id}}][{{$id}}]'; ?>">
                                                            </td>
                                                       @endforeach
                                                        <td class="total" style="text-align: center;font-weight: inherit;">
                                                            <input type="text" class="form-control" value="{{$total}}" name="total">
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <br><br>
                        <div class="col-lg-12 col-sm-12 col-xs-12">
                            <div class="table-responsive">
                                <table id="example" class="table table-striped" style="margin-top:40px; ">
                                    <tr style="border: 1px solid #000;">
                                        <td style="text-align: center;font-weight: inherit;border: 1px;">Old Student</td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <table border="1">
                                                <tr>
                                                    <th style="text-align: center;font-weight: inherit;">
                                                    {{ App\Helpers\get_string('studentquota','request')}}
                                                    </th>
                                                    @foreach ($data['data']['title_arr'] as $id => $val)
                                                        <th style="text-align: center;font-weight: inherit;">
                                                           {{ $val}}
                                                        </th>
                                                  @endforeach
                                                    <th style="text-align: center;font-weight: inherit;">
                                                        Total
                                                    </th>
                                                </tr>
                                                @foreach ($data['data']['quota_arr'] as $quota_id => $quota_val) 
                                                @php
                                                    $total = 0;
                                                @endphp
                                                    <tr>
                                                        <td style="text-align: center;font-weight: inherit;">
                                                             {{$quota_val}}
                                                        </td>
                                                        @foreach ($data['data']['title_arr'] as $id => $val) 
                                                        @php
                                                            $amount_val = 0;
                                                            if (isset($data['data']['bk_arr']['old'][$quota_id][$id])) {
                                                                $amount_val = $data['data']['bk_arr']['old'][$quota_id][$id];
                                                                $total += $amount_val;
                                                            }
                                                        @endphp
                                                            <td style="text-align: center;font-weight: inherit;">
                                                                <input type="text" class="form-control" value="{{$amount_val}}" name="OldValues[{{$quota_id}}][{{$id}}]">
                                                            </td>
                                                        @endforeach
                                                        <td class="total" style="text-align: center;font-weight: inherit;">
                                                            <input type="text" class="form-control" name="total" value="{{$total}}">
                                                        </td>
                                                   @endforeach
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <center><button type="submit" class="btn btn-info btn-outline btn">Submit</button></center>
                </form>
            </div>
        </div>
    </div>
</div>


@include('includes.footerJs')
<script>
    alert('here');


//    e.toggleClass("show-sidebar").toggleClass("hide-sidebar"), $(".sidebar-head .open-close i").toggleClass("ti-menu");
    $(document).ready(function () {
        
        $("input").each(function () {

            var that = this; // fix a reference to the <input> element selected
            $(this).keyup(function () {
//                alert("asdsa");
                newSum.call(that); // pass in a context for newsum():
                // call() redefines what "this" means
                // so newSum() sees 'this' as the <input> element
            });
        });
    });
    function newSum() {
        var sum = 0;
        var thisRow = $(this).closest('tr');

        thisRow.find('td:not(.total) input:text').each(function () {
            if (this.value != '') {
                sum += parseFloat(this.value); // or parseInt(this.value,10) if appropriate
            }
        });

        thisRow.find('td.total input:text').val(sum); // It is an <input>, right?
    }
</script>
@include('includes.footer')
