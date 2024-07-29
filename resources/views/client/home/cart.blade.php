@extends('client.main')

@section('title', 'Giỏ Hàng')

@section('styles')
@endsection

@section('main')
    <div class="row" id="list-courses">
        <div>
            <button class="btn-back mt-3">
                <i class="fa-solid fa-arrow-left"></i>
                &nbsp;Quay lại
            </button>
        </div>
        <h1 class="fw-bold mt-5 mb-3">Giỏ hàng</h1>
        <div class="col-xxl-8 col-xl-12">
            <ul class="responsive-table table-cart">
                <li class="table-header">
                    <div class="col col-1 d-flex gap-2">
                        <input type="checkbox" id="checkAll" class="form-check-input select-courses m-0" />
                        <label for="checkAll"> Tất cả (<span class="count-course">0</span> khóa học) </label>
                    </div>
                    <div class="col col-4" id="btn-removes">Xóa tất cả</div>
                </li>
                <div id="body-table" class="overflow-y-auto" style="max-height: 555px"></div>
            </ul>
        </div>
        <div class="col-xxl-4 col-xl-12">
            <div class="box-coupons box-shadow bg-light p-3 rounded">
                <div class="d-flex align-items-center mb-2">
                    <img src="./assets/icons/ticket.svg" height="30px" alt="" />
                    <span class="fs-5 fw-bold ms-2">Mã khuyến mãi</span>
                </div>
                <div class="form-coupon mb-3">
                    <input type="text" id="inputCode" placeholder="Nhập mã giảm giá" />
                    <button id="btn-apply" type="btn">Áp dụng</button>
                </div>
                <div class="d-flex flex-column gap-2 overflow-y-auto pb-1" id="list-coupons" style="max-height: 260px">
                </div>
            </div>
            <div class="box-summary box-shadow bg-light p-3 mt-3 rounded">
                <div class="d-flex justify-content-between fw-medium mb-2">
                    <span>Giá niêm yết:</span>
                    <span class="base-price">0 đ</span>
                </div>
                <div class="d-flex justify-content-between fw-medium mb-2">
                    <span>Giảm giá:</span>
                    <span class="reduce-price">0 đ</span>
                </div>
                <div class="d-flex justify-content-between fw-medium mb-3">
                    <span>Tạm tính:</span>
                    <span class="sub-total">0 đ</span>
                </div>
                <div class="fw-medium" id="list-codes">
                </div>
                <hr />
                <div class="d-flex justify-content-between fw-bold">
                    <span>Tổng giá:</span>
                    <span class="total-price">0 đ</span>
                </div>
            </div>
            <div class="col-md-12">
                <button id="btn-checkout" class="btn btn-info text-white text-uppercase fw-bold my-3 w-100 py-2">
                    Tiếp Tục
                </button>
            </div>
        </div>
        @if (count($listRecommend ?? []))
            <div class="col-md-12 mt-5">
                <h3 class="fw-bold">Các khóa học phổ biến khác:</h3>
                <div class="list-recommend wrapper-carousel">
                    <i id="left" class="fa-solid fa-angle-left"></i>
                    <ul id="carousel">
                        @foreach ($listRecommend ?? [] as $course)
                            <li class="box" data-id="{{ $course['id'] }}">
                                <div class="bg-light">
                                    <img src="{{ $course['thumbnail'] }}" alt="{{ $course['name'] }}" class='w-100 lazy'
                                        height="150px" />
                                    <div class="p-3 p-xxl-2">
                                        <p class="text-uppercase fw-semibold text-secondary mb-1">
                                            {{ $course['lecturer'] }}
                                        </p>
                                        <h5 class="fw-bold fs-5">
                                            {{ $course['name'] }}
                                            {{-- <a href="#" class="text-decoration-none"></a> --}}
                                        </h5>
                                        <p class="mb-0">
                                            <span class="text-secondary text-decoration-line-through me-1">
                                                {{ $course['fake_cost'] }}
                                            </span>
                                            <span class="fw-semibold">
                                                {{ $course['cost'] }}
                                            </span>
                                        </p>
                                        <p class="text-end mb-0 me-1">
                                            <a href="javascript:void(0)"><i class="fa-solid fa-arrow-right"></i></a>
                                        </p>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <i id="right" class="fa-solid fa-angle-right"></i>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('scripts')
    @if (count($listRecommend))
        <script src="{{ asset('assets/js/carousel.js') }}" defer></script>
    @endif
    <script>
        'use strict';
        var ids = new Set();
        var codes = new Set();
        var $listCoupons = $('#list-coupons');
        var $listCodes = $('#list-codes');
        var $bodyTable = $('#body-table');
        var $btnApply = $('#btn-apply');
        var $inputCode = $('#inputCode');
        var $countCourse = $('.count-course');
        var $inputSelectCourses = $('input.select-courses');
        var $btnCheckout = $('#btn-checkout');
        var $basePrice = $('.base-price');
        var $subTotal = $('.sub-total');
        var $reducePrice = $('.reduce-price');
        var $totalPrice = $('.total-price');
        var $btnRemoves = $('#btn-removes'); //remove All

        $document.ready(loadData);
        // $document.ajaxStart(startLoading);
        // $document.ajaxStop(stopLoading);

        $document.on('click', '.btn-back, .btn-discovery', function() {
            window.location.href = '/';
        });

        function loadData() {
            $.get("{{ route('carts.list') }}").done(response => {
                if (response.data) {
                    $bodyTable.append(
                        response.data.map(cart =>
                            boxCourse(
                                cart.id,
                                cart.thumbnail,
                                cart.name,
                                cart.lecturer,
                                cart.fake_cost,
                                cart.cost,
                                cart.duration
                            )
                        ).join('')
                    );
                    Summary();
                } else {
                    showEmptyBox();
                }
            });
        }

        function updateCourseChecked() {

            //count courses selected
            let $selected = $('input.select-course:checked');
            $countCourse.text($selected.length);

            //update ids
            ids.clear();
            $selected.each(function() {
                ids.add($(this).closest('.table-row').data('id'));
            });

            let $inputSelectCourse = $('input.select-course');
            $inputSelectCourses.prop('checked', $selected.length === $inputSelectCourse.length);


            $btnRemoves.toggleClass('text-danger', $selected.length > 0)
                .css('cursor', $selected.length > 0 ? 'pointer' : 'default');
            Summary();
        }

        function updateCodeChecked() {
            codes.clear();
            $('input.select-code:checked').each(function() {
                codes.add($(this).val());
            });
        }

        function Summary() {
            $.get("{{ route('carts.summary') }}", {
                ids: Array.from(ids),
                codes: Array.from(codes)
            }).done(response => {
                $listCoupons.empty();
                $listCodes.empty();
                const {
                    basePrice,
                    reducePrice,
                    subTotal,
                    totalPrice,
                    codes: resCodes,
                    coupons
                } = response.data;
                boxSummary(basePrice, reducePrice, subTotal, totalPrice);
                codes = new Set(Object.keys(resCodes) ?? []);
                if (resCodes) {
                    $listCodes.append(Object.entries(resCodes).map(([key, value]) =>
                        `<div class="d-flex justify-content-between fw-medium mb-2">
                                 <span>Mã ưu đãi (${key})</span>
                                 <span class="reduce-price">- ${value}</span>
                             </div>`).join(''));
                }
                if (coupons?.data?.length) {
                    $listCoupons.append(
                        coupons.data.map(coupon => {
                            return boxCoupons(
                                coupon.code,
                                coupon.description,
                                Object.keys(resCodes).includes(coupon.code),
                                coupons.limit
                            );
                        }).join('')
                    );
                }
            });
        }

        $btnApply.click(() => {
            let code = $inputCode.val().trim();
            if (code !== '') {
                codes.add(code);
                Summary();
                $inputCode.val('');
            }
        })

        //function remove course
        function removeCourse(_this, callback) {
            _this = _this.closest('.table-row');
            $.ajax({
                url: "{{ route('carts.remove') }}",
                type: 'DELETE',
                data: {
                    id: _this.data('id')
                }
            }).done(response => {
                if (response.data.count) {
                    _this.remove();
                    callback(response);
                } else {
                    showEmptyBox();
                }
            }).fail(() => callback({
                success: false
            })).always(handleCountCart);
        }

        //Remove a course
        $document.on('click', '.btn-remove', function(e) {
            e.stopPropagation();
            if (confirm('Bạn có chắc chắn xóa khóa học này!')) {
                removeCourse($(this), () => updateCourseChecked());
            }
        });

        //Remove courses
        $btnRemoves.click(function() {
            let $selected = $('input.select-course:checked');
            if ($selected.length !== 0) {
                if (confirm(`Bạn có chắc chắn xóa ${$selected.length} khóa học này!`)) {
                    let removePromises = $selected.map(function() {
                        return new Promise((resolve) => {
                            removeCourse($(this), resolve);
                        });
                    }).get();

                    Promise.all(removePromises)
                        .then(() => updateCourseChecked())
                        .catch(() => {
                            console.error('Error occurred while removing courses');
                        });
                }
            }
        });

        $btnCheckout.click(function() {
            let $selected = $('input.select-course:checked');
            if ($selected.length !== 0) {
                let text = $(this).text();
                $(this).html(spanSpinner).addClass('disabled');
                $.post("{{ route('carts.checkout') }}", {
                    ids: Array.from(ids),
                    codes: Array.from(codes)
                }).done(response => {
                    window.location.href = response.data?.url ?? '';
                }).fail(() => {
                    $(this).text(text).removeClass('disabled');
                });
            }
        });

        $document.on('click', 'input.select-courses', function() {
            let inputSelectCourse = $('input.select-course');
            inputSelectCourse.prop('checked', this.checked);
            updateCourseChecked();
        });

        $document.on('change', 'input.select-course', updateCourseChecked);

        $document.on('click', '.table-cart .table-row', function(e) {
            if (!$(e.target).is('.select-course, .link-course')) {
                let $inputCheck = $(this).find('.select-course');
                $inputCheck[0].checked = !$inputCheck[0].checked;
                $inputCheck.trigger('change');
            }
        });

        $document.on('change', 'input.select-code', function() {
            let code = $(this).val();
            $(this).is(':checked') ? codes.add(code) : codes.delete(code);
            Summary();
        });

        $document.on('click', '.box-select-code', function(e) {
            if (!$(e.target).is('.form-check-input, .form-check-label, .icon-info, .tooltip-text')) {
                let $inputCheck = $(this).find('.select-code');
                if (!$inputCheck.is(':disabled')) {
                    $inputCheck[0].checked = !$inputCheck[0].checked;
                    $inputCheck.trigger('change');
                }
            }
        });

        $document.on('mouseenter mouseleave', '.box-select-code .icon-info', function(e) {
            let $tooltip = $(this).closest('.box-select-code').find('.tooltip-text');
            if (e.type === 'mouseenter') {
                $tooltip.addClass('show');
            } else if (e.type === 'mouseleave') {
                $tooltip.removeClass('show');
            }
        });

        //Render UI
        function boxCourse(id, thumbnail, name, author, fake_cost, cost, duration) {
            return (`<li class="table-row" data-id='${id}'>
                         <div class="col col-1">
                             <input type='checkbox' class="form-check-input select-course mt-0" />
                             <img src='${thumbnail}' height='150px' alt='${name}' class='lazy'/>
                             <div class="info-course">
                                 <a href='javascript:void(0)' class="link-course fw-bold fs-5">${name}</a>
                                 <p class='mt-1'>Bởi ${author}</p>
                                 <p>
                                     <i class="fa-regular fa-clock"></i> ${duration} giờ
                                 </p>
                             </div>
                         </div>
                         <div class="col col-2 fw-bold">
                             <p class="text-dash">${fake_cost}</p>
                             <p>${cost}</p>
                         </div>
                         <div class="col col-4">
                             <button type="button" class="btn btn-danger btn-remove">
                                 <i class="fa-solid fa-trash"></i>
                             </button>
                         </div>
                     </li>`);
        }


        function boxSummary(base, reduce, sub, total) {
            $basePrice.text(base);
            $reducePrice.text('- ' + reduce);
            $subTotal.text(sub);
            $totalPrice.text(total);
        }

        function showEmptyBox() {
            $main.empty().append(boxEmpty()).css({
                'display': 'flex',
                'justify-content': 'center',
                'align-items': 'center'
            });
        }

        function boxCoupons(code, desc, isChecked = false, disable = false) {
            return (`<div class="box-select-code d-flex justify-content-between align-items-center border p-2 rounded">
                         <div class="form-check">
                             <input class="form-check-input select-code"
                                 type="checkbox"
                                 value="${code}"
                                 id="${code}"
                                 ${isChecked? 'checked': ''}
                                 ${disable && !isChecked ? 'disabled': ''}/>
                             <label class="form-check-label w-100" for="${code}">${code}</label>
                         </div>
                         <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                             stroke="currentColor" class="icon-info">
                             <path stroke-linecap="round" stroke-linejoin="round"
                                 d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                         </svg>
                         <span class='tooltip-text'>${desc}</span>
                     </div>`);
        }

        function boxEmpty() {
            return (`<div class="text-center">
                         <div>
                            <img src="./assets/icons/cart-empty.svg" alt="" />
                         </div>
                         <p>Giỏ hàng của bạn đang trống.</p>
                         <p>Hãy thêm khóa học vào giỏ hàng nhé!</p>
                         <button class="btn-discovery btn btn-info text-white">
                             Khám phá khóa học
                         </button>
                     </div>`);
        }
    </script>
@endsection
