<?php
return PerformanceAspect::measure('Checkout', function () {
    return $this->checkout();
});
