@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')

<div id="page-wrapper">
    <div class="container-fluid">
      <div class="row bg-title">
          <div class="col-lg-3 col-md-4 col-sm-4 col-xs-12">
              <h4 class="page-title">Site Map</h4> 
          </div>
      </div>
      <div class="card">
        @if ($message = Session::get('success'))
        <div class="alert alert-success alert-block">
            <button type="button" class="close" data-dismiss="alert">×</button>
            <strong>{{ $message }}</strong>
        </div>
        @endif
        @php
          if(!empty($data['groupwisemenuMaster']))
          {
            $groupwisemenuMaster = $data['groupwisemenuMaster'];
          }

          if(!empty($data['groupwisesubmenuMaster']))
          {
            $groupwisesubmenuMaster = $data['groupwisesubmenuMaster'];
          }

          if(!empty($data['groupwiseSubsubmenuMaster']))
          {
            $groupwiseSubsubmenuMaster = $data['groupwiseSubsubmenuMaster'];
          }

        @endphp
        <div class="row">          
            <div class="col-md-12">
                <div class="table-responsive" id="groupwiseRightsTable">
                    <table class="table table-bordered table-striped responsive-utilities" id="example">
                        <thead>
                            <tr>
                                <th style="text-align: center;"> Menu Name </th>
                                <th style="text-align: center;"> <i class="mdi mdi-youtube-play fa-fw"></i>Youtube Link </th>
                                <th style="text-align: center;"> <i class="mdi mdi-file-pdf fa-fw"></i>Pdf Link </th>
                                <th style="text-align: center;"> Menu Path </th>
                            </tr>
                        </thead>
                        <tbody>
                        @if(!empty($groupwisemenuMaster))
                        @foreach($groupwisemenuMaster as $key => $value)
                            <tr>
                                <td><b>{{ $value['site_map_name'] }}</b></td>
                                <td style="text-align: center;">
                                        <label for="add_{{ $value['id'] }}"> <a href="{{ $value['youtube_link'] }}" target="_blank"><i style="color: #ff0000;" class="mdi mdi-youtube-play fa-fw"></i></a> </label>
                                </td>
                                <td style="text-align: center;">
                                        <label for="edit_{{ $value['id'] }}"> <a href="{{ $value['pdf_link'] }}" target="_blank"><i style="color: #ff0000;" class="mdi mdi-file-pdf fa-fw"></i></a> </label>
                                </td>
                                <td style="text-align: center;">
                                        <label for="delete_{{ $value['id'] }}"> {{ $value['menu_path'] }} </label>
                                </td>
                            </tr>
                            @if(!empty($groupwisesubmenuMaster[$value['id']]))
                            @foreach($groupwisesubmenuMaster[$value['id']] as $submenuKey => $submenuValue)
                            <tr>
                                <td style="text-align: center;">{{ $submenuValue['site_map_name'] }}</td>
                                <td style="text-align: center;">
                                        <label for="add_{{ $value['id'] }}"> <a href="{{ $submenuValue['youtube_link'] }}" target="_blank"><i style="color: #ff0000;" class="mdi mdi-youtube-play fa-fw"></i></a> </label>
                                </td>
                                <td style="text-align: center;">
                                        <label for="edit_{{ $value['id'] }}"> <a href="{{ $submenuValue['pdf_link'] }}" target="_blank"><i style="color: #ff0000;" class="mdi mdi-file-pdf fa-fw"></i></a> </label>
                                </td>
                                <td style="text-align: center;">
                                        <label for="delete_{{ $value['id'] }}"> {{ $submenuValue['menu_path'] }} </label>
                                </td>
                            </tr>
                                @if(!empty($groupwiseSubsubmenuMaster[$submenuValue['id']]))
                                  @foreach($groupwiseSubsubmenuMaster[$submenuValue['id']] as $SubsubmenuKey => $SubsubmenuValue)
                                  <tr>
                                      <td style="text-align: center;">{{ $SubsubmenuValue['site_map_name'] }}</td>
                                      <td style="text-align: center;">
                                        <label for="add_{{ $value['id'] }}"> <a href="{{ $SubsubmenuValue['youtube_link'] }}" target="_blank"><i style="color: #ff0000;" class="mdi mdi-youtube-play fa-fw"></i></a> </label>
                                      </td>
                                      <td style="text-align: center;">
                                              <label for="edit_{{ $value['id'] }}"> <a href="{{ $SubsubmenuValue['pdf_link'] }}" target="_blank"><i style="color: #ff0000;" class="mdi mdi-file-pdf fa-fw"></i></a> </label>
                                      </td>
                                      <td style="text-align: center;">
                                          <label for="delete_{{ $value['id'] }}"> {{ $SubsubmenuValue['menu_path'] }} </label>
                                      </td>
                                  </tr>
                                  @endforeach
                                @endif
                            @endforeach
                            @endif
                        @endforeach
                        @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
      </div>
    </div>
</div>
@include('includes.footerJs')
<script>
$(document).ready(function () {
    $('#example').DataTable();
});

</script>
@include('includes.footer')
