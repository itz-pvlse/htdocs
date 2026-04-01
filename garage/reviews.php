<?php if (isset($_SESSION['user_id'])): // Only logged-in users can review ?>
    <h2 class="text-lg font-semibold mt-8">Leave a Review</h2>
    <form action="submit_review.php" method="POST" class="space-y-4">
        <div>
            <label for="rating" class="block">Rating (1 to 5):</label>
            <input type="number" name="rating" min="1" max="5" required class="border p-2 w-full">
        </div>
        
        <div>
            <label for="review_text" class="block">Review:</label>
            <textarea name="review_text" rows="4" class="border p-2 w-full" placeholder="Write your review..."></textarea>
        </div>

        <input type="hidden" name="garage_id" value="<?= $garage_id ?>"> <!-- For the current garage -->
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Submit Review</button>
    </form>
<?php endif; ?>