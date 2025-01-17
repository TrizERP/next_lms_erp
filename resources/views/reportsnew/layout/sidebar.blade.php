            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="menu-inner-shadow"></div>
                <ul class="menu-inner py-1">
                    <li class="menu-item bar_Menu">
                        <a href="bar-chart" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Bar Chart</div>
                        </a>
                    </li>

                    <!-- <li class="menu-item buble_Menu {{ request()->is('bubble-chart') ? 'active' : '' }}">
                        <a href="bubble-chart" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Buble Chart</div>
                        </a>
                    </li> -->

                    <li class="menu-item doughnut_Menu {{ request()->is('doughnut-chart') ? 'active' : '' }}">
                        <a href="doughnut-chart" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Doughnut Chart</div>
                        </a>
                    </li>
                    <!-- <li class="menu-item horizontal_Menu {{ request()->is('horizontal-bar-chart') ? 'active' : '' }}">
                        <a href="horizontal-bar-chart" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Horizontal Bar Chart</div>
                        </a>
                    </li> -->
                    <!-- <li class="menu-item mixed_Menu {{ request()->is('polar-area-chart') ? 'active' : '' }}">
                        <a href="polar-area-chart" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">polar-area-chart</div>
                        </a>
                    </li> -->
                    <!-- <li class="menu-item mixed_Menu {{ request()->is('real-time-chart') ? 'active' : '' }}">
                        <a href="real-time-chart" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Real Time Chart</div>
                        </a>
                    </li> -->
                    <li class="menu-item scatter-line-chart {{ request()->is('scatter-line-chart') ? 'active' : '' }}">
                        <a href="scatter-line-chart" class="menu-link">
                            <i class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div data-i18n="Analytics">Scatter Line Chart</div>
                        </a>
                    </li>

                </ul>
            </aside>
