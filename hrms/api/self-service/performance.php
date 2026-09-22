<?php
define('HRMS_METHOD','GET');
require_once __DIR__.'/../../includes/bootstrap.php';
$employee=ownEmployee();
$reviews=rows('SELECT review_id,cycle_id,employee_id,review_status,self_assessment,employee_submitted_at,finalized_at FROM performance_reviews WHERE employee_id=? ORDER BY review_id DESC'.pageLimit(),[$employee]);
foreach ($reviews as &$review) {
    if (in_array($review['review_status'],['Finalized','Closed'],true)) {
        $review+=one('SELECT overall_rating,manager_comments,strengths,areas_for_improvement,development_plan FROM performance_reviews WHERE review_id=?',[(int)$review['review_id']]);
        $review['ratings']=rows('SELECT goal_id,rating,comments FROM performance_goal_ratings WHERE review_id=?',[(int)$review['review_id']]);
    }
}
unset($review);
reply(['employee_id'=>$employee,'goals'=>rows('SELECT g.*,c.cycle_name,c.status cycle_status FROM performance_goals g JOIN performance_cycles c ON c.cycle_id=g.cycle_id WHERE employee_id=? ORDER BY goal_id DESC'.pageLimit(),[$employee]),'reviews'=>$reviews]);
