@include('includes.headcss') @include('includes.header') @include('includes.sideNavigation')
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.8.0/html2pdf.bundle.min.js"></script>
<style>

.ag-format-container {
  width: 1142px;
}

.ag-courses_box {
  display: -webkit-box;
  display: -ms-flexbox;
  display: flex;
  -webkit-box-align: start;
  -ms-flex-align: start;
  align-items: flex-start;
  -ms-flex-wrap: wrap;
  flex-wrap: wrap;

  padding: 50px 0;
}
.ag-courses_item {
  -ms-flex-preferred-size: calc(33.33333% - 30px);
  flex-basis: calc(33.33333% - 30px);

  margin: 0 15px 30px;

  overflow: hidden;

  border-radius: 28px;
}
.ag-courses-item_link {
  display: block;
  padding: 30px 20px;
  background-color: #fff;

  overflow: hidden;

  position: relative;
}
.ag-courses-item_link:hover,
.ag-courses-item_link:hover{
  text-decoration: none;
  color: #FFF;
}
.ag-courses-item_link:hover .ag-courses-item_bg {
  -webkit-transform: scale(10);
  -ms-transform: scale(10);
  transform: scale(10);
}
.ag-courses-item_title {
  min-height: 87px;
  margin: 0 0 25px;

  overflow: hidden;

  font-weight: bold;
  font-size: 30px;
  color: #726e6e;

  z-index: 2;
  position: relative;
}
.ag-courses-item_date-box {
  font-size: 18px;
  color: #726e6e;

  z-index: 2;
  position: relative;
}
.ag-courses-item_date {
  font-weight: bold;
  color: #726e6e;

  -webkit-transition: color .5s ease;
  -o-transition: color .5s ease;
  transition: color .5s ease
}
.ag-courses-item_bg {
  height: 128px;
  width: 128px;
  background-color: #f9b234;

  z-index: 1;
  position: absolute;
  top: -75px;
  right: -75px;

  border-radius: 50%;

  -webkit-transition: all .5s ease;
  -o-transition: all .5s ease;
  transition: all .5s ease;
}
.ag-courses_item:nth-child(2n) .ag-courses-item_bg,.ag-courses_item:nth-child(7n) .ag-courses-item_bg  {
  background-color: #3ecd5e;
}
.ag-courses_item:nth-child(3n) .ag-courses-item_bg,.ag-courses_item:nth-child(8n) .ag-courses-item_bg  {
  background-color: #e44002;
}
.ag-courses_item:nth-child(4n) .ag-courses-item_bg,.ag-courses_item:nth-child(9n) .ag-courses-item_bg  {
  background-color: #952aff;
}
.ag-courses_item:nth-child(5n) .ag-courses-item_bg,.ag-courses_item:nth-child(10n) .ag-courses-item_bg  {
  background-color: #cd3e94;
}
.ag-courses_item:nth-child(6n) .ag-courses-item_bg,.ag-courses_item:nth-child(11n) .ag-courses-item_bg  {
  background-color: #4c49ea;
}

@media only screen and (max-width: 979px) {
  .ag-courses_item {
    -ms-flex-preferred-size: calc(50% - 30px);
    flex-basis: calc(50% - 30px);
  }
  .ag-courses-item_title {
    font-size: 24px;
  }
}

@media only screen and (max-width: 767px) {
  .ag-format-container {
    width: 96%;
  }

}
@media only screen and (max-width: 639px) {
  .ag-courses_item {
    -ms-flex-preferred-size: 100%;
    flex-basis: 100%;
  }
  .ag-courses-item_title {
    min-height: 72px;
    line-height: 1;

    font-size: 24px;
  }
  .ag-courses-item_link {
    padding: 22px 40px;
  }
  .ag-courses-item_date-box {
    font-size: 16px;
  }
}
</style>
<div id="page-wrapper">
	<div class="container-fluid">
		
<!-- new  -->
<div class="ag-format-container">
  <div class="ag-courses_box">
  @if(!empty($data['result_data']))
  @foreach($data['result_data'] as $key=>$value)  
    <div class="ag-courses_item">
      <a href='/result/all_results/create?id={{$value->id}}' target='_blank' class="ag-courses-item_link">
        <div class="ag-courses-item_bg"></div>
        <div class="ag-courses-item_title">
            <h4><b>Standard  : {{$value->name}}</b></h4>
            <h4><b>Term Name : {{$value->title}}</b></h4>
        </div>

        <div class="ag-courses-item_date-box">
          year:
          <span class="ag-courses-item_date">
            {{$value->syear}}
          </span>
        </div>

         <div class="ag-courses-item_date-box" style="font-size:0.8rem">Click to View</div>
      </a>
    </div>
    @endforeach
    @else
    <div class="ag-courses_item">
      <a href='#' target='_blank' class="ag-courses-item_link">
        <div class="ag-courses-item_bg"></div>
        <div class="ag-courses-item_title">
            <h4><b>Standard  : None</b></h4>
            <h4><b>Term Name : None</b></h4>
        </div>

        <div class="ag-courses-item_date-box">
          year:
          <span class="ag-courses-item_date">
          None
          </span>
        </div>

         <div class="ag-courses-item_date-box" style="font-size:0.8rem">No result report card added by teachers</div>
      </a>
    </div>
    @endif
  </div>
</div>
<!-- new end  -->
	</div>
</div>

@include('includes.footerJs') 
<script type="text/javascript">

</script>
@include('includes.footer')