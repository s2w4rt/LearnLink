<?php
// assignment-card.php - Reusable assignment card component
$statusColor = 'gray';
$statusText = 'Not Started';

if (!empty($assignment['submission_status'])) {
    switch ($assignment['submission_status']) {
        case 'submitted':
            $statusColor = 'blue';
            $statusText = 'Submitted';
            break;
        case 'graded':
            $statusColor = 'green';
            $statusText = 'Graded';
            break;
        case 'late':
            $statusColor = 'red';
            $statusText = 'Late';
            break;
        default:
            $statusColor = 'yellow';
            $statusText = 'Assigned';
    }
}

$isOverdue = strtotime($assignment['due_date']) < strtotime('today');
$dueDateClass = $isOverdue ? 'text-red-600' : 'text-gray-600';
?>

<div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
    <div class="flex justify-between items-start mb-2">
        <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($assignment['title']); ?></h3>
        <span class="bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800 text-xs px-2 py-1 rounded-full">
            <?php echo $statusText; ?>
        </span>
    </div>
    
    <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($assignment['description']); ?></p>
    
    <div class="flex justify-between items-center text-sm">
        <div>
            <span class="text-gray-500">Due:</span>
            <span class="<?php echo $dueDateClass; ?> font-medium">
                <?php echo date('M j, Y', strtotime($assignment['due_date'])); ?>
            </span>
        </div>
        <div class="text-right">
            <span class="text-gray-500">Points:</span>
            <span class="font-medium"><?php echo $assignment['max_score']; ?></span>
        </div>
    </div>
    
    <?php if ($assignment['submission_status'] === 'graded' && !empty($assignment['score'])): ?>
        <div class="mt-2 p-2 bg-green-50 rounded border border-green-200">
            <div class="flex justify-between text-sm">
                <span class="text-green-800">Score:</span>
                <span class="font-bold text-green-800">
                    <?php echo $assignment['score']; ?>/<?php echo $assignment['max_score']; ?>
                </span>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="mt-3 flex space-x-2">
        <?php if (empty($assignment['submission_status']) || $assignment['submission_status'] === 'assigned'): ?>
            <a href="submit-assignment.php?id=<?php echo $assignment['id']; ?>" 
               class="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-center py-2 px-3 rounded text-sm font-medium transition-colors">
                Submit
            </a>
        <?php endif; ?>
        
        <a href="assignment-details.php?id=<?php echo $assignment['id']; ?>" 
           class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 text-center py-2 px-3 rounded text-sm font-medium transition-colors">
            Details
        </a>
    </div>
</div>