@extends('client.main')

@section('title', 'Trang Chủ')

@section('styles')
    <style>
    </style>
@endsection

@section('main')
    <div class="row" id="list-courses">
    </div>
@endsection

@section('scripts')
    <script>
        var listCourses = $('#list-courses');

        function loadData() {
            listCourses.empty();
            $.get('/courses').done(function(response) {
                if (response.success) {
                    const courses = response.courses;
                    if (courses.length) {
                        listCourses.append(
                            courses.map(
                                course =>
                                card(course.id, course.name, course.fake_cost, course.cost, course.thumbnail)
                            ).join('')
                        );
                    } else {
                        listCourses.append(`<idv class='text-center'>KHÔNG CÓ KHÓA HỌC NÀO!</div>`);
                    }
                } else {
                    listCourses.append(`<idv class='text-center'>${response.message}</div>`);
                }
            });
        }

        function card(id, name, fake_cost, cost, thumbnail) {
            return `<div class="col-lg-3 col-md-6 mb-3 mt-3">
                        <div class="course-box" data-id='${id}'>
                                <img src='${thumbnail}' alt='${name}' class="course-img lazy">
                                <h2 class="course-title">${name}</h2>
                                <span class="course-price">${formatCurrency(cost)}</span>
                                <button type="button" id="btn-add-cart">
                                    Thêm vào giỏ hàng
                                </button>
                            </div>
                        </div>`;
        }

        $document.on('click', '#btn-add-cart', function() {
            let text = $(this).text();
            $(this).text('').html(spanSpinner).prop("disabled", true);
            let id = $(this).closest('.course-box').data('id');
            $.post("{{ route('carts.add') }}", {
                    id
                })
                .done(handleCountCart)
                .always(() => {
                    $(this).text(text).prop("disabled", false);
                });
        });

        $document.ready(function() {
            loadData();
        });
    </script>
@endsection
