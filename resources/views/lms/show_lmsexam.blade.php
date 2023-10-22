{{--@include('includes.headcss')
@include('includes.header')
@include('includes.sideNavigation')--}}
@extends('layout')
@section('container')
<!-- Content main Section -->
                <div class="content-main flex-fill">
                    <div class="row">
                        <div class="col-md-6 text-right">
                            <div class="course-select-grid">
                                <select class="cust-select form-control mb-0">
                                    <option>Semester-2</option>
                                    <option>Semester-1</option>
                                </select>
                                <div class="course-lg-tab">
                                    <ul class="nav">
                                        <li>
                                            <a href="#" class="nav-link"><i class="mdi mdi-calendar-month-outline"></i></a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="container-fluid mb-5">
                        <div class="course-grid-tab tab-pane fade show active" id="grid" role="tabpanel" aria-labelledby="grid-tab">
                            <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                                <li class="nav-item">
                                  <a class="nav-link" id="message-tab" data-toggle="pill" href="#message" role="tab" aria-controls="pills-profile" aria-selected="false">Practice Test</a>
                                </li>
                                <li class="nav-item">
                                  <a class="nav-link" id="chat-tab" data-toggle="pill" href="#chat" role="tab" aria-controls="chat-tab" aria-selected="false">Exam</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link active" id="notification-tab" data-toggle="pill" href="#notification" role="tab" aria-controls="pills-home" aria-selected="true">Homework</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="groupchat-tab" data-toggle="pill" href="#groupchat" role="tab" aria-controls="groupchat-tab" aria-selected="false">Assignment</a>
                                </li>
                            </ul>
                            <div class="tab-content" id="pills-tabContent">
                                <div class="tab-pane fade show active" id="notification" role="tabpanel" aria-labelledby="notification-tab">
                                    <form class="row">
                                        @csrf
                                        <div class="col-md-3 form-group">
                                            <select class="cust-select form-control border-0" id="subject" tabindex="-98">
                                                <option>Select</option>
                                                <option>Gujarati</option>
                                                <option>Hindi</option>
                                                <option>English</option>
                                                <option>Science</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 form-group">
                                            <select class="cust-select form-control border-0" id="subject" tabindex="-98">
                                                <option>Select</option>
                                                <option>Gujarati</option>
                                                <option>Hindi</option>
                                                <option>English</option>
                                                <option>Science</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 form-group">
                                            <button type="submit" class="btn btn-primary">Search</button>
                                        </div>
                                    </form>
                                    <div class="notification-box card d-block">
                                        <div class="noti-title">Spelling Test Tomorrow!</div>
                                        <div class="noti-des">We assign to math's force measure lorem Ipsum is simply dummy text of the printing and typesetting industry.</div>
                                        <a href="#" class="btn btn-primary mt-3">Submit</a>
                                        <div class="time">5 Days ago</div>
                                    </div>
                                    <div class="notification-box card d-block">
                                        <div class="noti-title">Spelling Test Tomorrow!</div>
                                        <div class="noti-des">We assign to math's force measure lorem Ipsum is simply dummy text of the printing and typesetting industry.</div>
                                        <a href="#" class="btn btn-primary mt-3">Submit</a>
                                        <div class="time">5 Days ago</div>
                                    </div>
                                    <div class="notification-box card d-block">
                                        <div class="noti-title">Spelling Test Tomorrow!</div>
                                        <div class="noti-des">We assign to math's force measure lorem Ipsum is simply dummy text of the printing and typesetting industry.</div>
                                        <a href="#" class="btn btn-primary mt-3">Submit</a>
                                        <div class="time">5 Days ago</div>
                                    </div>
                                    <div class="notification-box card d-block">
                                        <div class="noti-title">Spelling Test Tomorrow!</div>
                                        <div class="noti-des">We assign to math's force measure lorem Ipsum is simply dummy text of the printing and typesetting industry.</div>
                                        <a href="#" class="btn btn-primary mt-3">Submit</a>
                                        <div class="time">5 Days ago</div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="message" role="tabpanel" aria-labelledby="message-tab">Practice Test</div>
                                <div class="tab-pane fade" id="chat" role="tabpanel" aria-labelledby="chat-tab">
                                    <div class="card border-0 rounded mb-5">
                                        <div class="card-body">
                                            <div class="d-md-flex align-items-center justify-content-between">
                                                <div class="quiz-labels">
                                                    <div class="h4">Quiz Navigation</div>
                                                    <ul class="quiz-navigation">
                                                        <li class="active">1</li>
                                                        <li>2</li>
                                                        <li>3</li>
                                                        <li>4</li>
                                                    </ul>
                                                </div>
                                                <div class="quiz-time">
                                                    <a href="#" class="btn btn-outline-primary mb-3">Start a new Preview</a>
                                                    <div class="color-primary mb-2">Finish Attemp..</div>
                                                    <div class="text-secondary">Time Left: 00 : 12 : 03</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="container-fluid mb-4">
                                        <div class="quiz-box">
                                            <div class="row mb-3">
                                                <div class="col-2">
                                                    <div class="quiz-box-count">
                                                        <div class="count">1</div>
                                                        <div class="quiz-con">
                                                            <div class="text-secondary mb-2">Marked out of</div>
                                                            <div class="text-secondary mb-2">1:00</div>
                                                            <div class="text-secondary"><i class="mdi mdi-flag-outline"></i></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-10">
                                                    <div class="card border-0 rounded">
                                                        <div class="card-body">
                                                            <div class="quiz-title">Lorem Ipsum Dolor sit Amet</div>
                                                            <div class="quiz-option">
                                                                <div class="title">Select One</div>
                                                                <ul>
                                                                    <li>
                                                                        <div class="custom-control custom-radio custom-control-inline">
                                                                            <input type="radio" id="customRadioInline1" name="customRadioInline1" class="custom-control-input">
                                                                            <label class="custom-control-label" for="customRadioInline1">Toggle this custom radio</label>
                                                                        </div>
                                                                    </li>
                                                                    <li>
                                                                        <div class="custom-control custom-radio custom-control-inline">
                                                                            <input type="radio" id="customRadioInline2" name="customRadioInline1" class="custom-control-input">
                                                                            <label class="custom-control-label" for="customRadioInline2">Or toggle this other custom radio</label>
                                                                        </div>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-2">
                                                    <div class="quiz-box-count">
                                                        <div class="count">1</div>
                                                        <div class="quiz-con">
                                                            <div class="text-secondary mb-2">Marked out of</div>
                                                            <div class="text-secondary mb-2">1:00</div>
                                                            <div class="text-secondary"><i class="mdi mdi-flag-outline"></i></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-10">
                                                    <div class="card border-0 rounded">
                                                        <div class="card-body">
                                                            <div class="quiz-title">Lorem Ipsum Dolor sit Amet</div>
                                                            <div class="quiz-option">
                                                                <div class="title">Select One</div>
                                                                <ul>
                                                                    <li>
                                                                        <div class="custom-control custom-radio custom-control-inline">
                                                                            <input type="radio" id="customRadioInline21" name="customRadioInline2" class="custom-control-input">
                                                                            <label class="custom-control-label" for="customRadioInline21">Toggle this custom radio</label>
                                                                        </div>
                                                                    </li>
                                                                    <li>
                                                                        <div class="custom-control custom-radio custom-control-inline">
                                                                            <input type="radio" id="customRadioInline22" name="customRadioInline2" class="custom-control-input">
                                                                            <label class="custom-control-label" for="customRadioInline22">Or toggle this other custom radio</label>
                                                                        </div>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-2">
                                                    <div class="quiz-box-count">
                                                        <div class="count">1</div>
                                                        <div class="quiz-con">
                                                            <div class="text-secondary mb-2">Marked out of</div>
                                                            <div class="text-secondary mb-2">1:00</div>
                                                            <div class="text-secondary"><i class="mdi mdi-flag-outline"></i></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-10">
                                                    <div class="card border-0 rounded">
                                                        <div class="card-body">
                                                            <div class="quiz-title">Lorem Ipsum Dolor sit Amet</div>
                                                            <div class="quiz-option">
                                                                <div class="title">Select One</div>
                                                                <ul>
                                                                    <li>
                                                                        <div class="custom-control custom-radio custom-control-inline">
                                                                            <input type="radio" id="customRadioInline31" name="customRadioInline3" class="custom-control-input">
                                                                            <label class="custom-control-label" for="customRadioInline31">Toggle this custom radio</label>
                                                                        </div>
                                                                    </li>
                                                                    <li>
                                                                        <div class="custom-control custom-radio custom-control-inline">
                                                                            <input type="radio" id="customRadioInline32" name="customRadioInline3" class="custom-control-input">
                                                                            <label class="custom-control-label" for="customRadioInline32">Or toggle this other custom radio</label>
                                                                        </div>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="groupchat" role="tabpanel" aria-labelledby="groupchat-tab">Assignment</div>
                            </div>
                        </div>
                    </div>

                </div>

@include('includes.footerJs')
@include('includes.footer')
@endsection
