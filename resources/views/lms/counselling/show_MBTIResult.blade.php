@include('includes.lmsheadcss')
<style>
p {
    /* font-family: 'Poppins', sans-serif; */
    color: #000000;
    padding-top: 10px;
}
.course-grid-tab ul {
    border-bottom: none;
}
body {
    font-size: 15px;
    line-height: 20px;
    color: #000000;
    background-color: #f0f0f8;
}
</style>
<!-- Content main Section -->
<div class="content-main flex-fill">
    <div class="container-fluid mb-5">
        <div class="course-grid-tab tab-pane fade show active" id="grid" role="tabpanel" aria-labelledby="grid-tab">
                                                        
            <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show active" id="chat" role="tabpanel" aria-labelledby="chat-tab">
                    <div class="card border-0 rounded mb-5">
                        <div class="card-body">
                            <div class="d-md-flex align-items-center justify-content-between">                            
                                {!!$data['answer_data']!!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>            
        </div>
    </div>
</div>





