<?php
class ModelExtensionModuleFactoryUpdates extends Model {
	/**
	 * The Miner Island — Factory Updates admin model.
	 * Phase 7.1 Step 3: full CRUD — list, add, edit, delete + seo_url maintenance.
	 *
	 * SEO URL contract:
	 *   - Each post row in oc_factory_update has a unique `slug`.
	 *   - For each post, there is exactly one oc_seo_url row with
	 *     query = 'factory_post_id=N' and keyword = <slug>, store_id = 0,
	 *     language_id = 1 (the default language).
	 *   - On addPost: insert post, then insert seo_url row.
	 *   - On editPost: update post; if slug changed, update seo_url.keyword
	 *     (UPDATE — never INSERT a duplicate).
	 *   - On deletePost: remove the seo_url row first, then the post row.
	 */

	/* ---------------------------------------------------------------------- *
	 * READ HELPERS
	 * ---------------------------------------------------------------------- */

	public function getPost($post_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "factory_update WHERE factory_post_id = '" . (int)$post_id . "'");

		return $query->row;
	}

	public function getPosts($data = array()) {
		$sql = "SELECT factory_post_id, title, slug, published_at, status, sort_order FROM " . DB_PREFIX . "factory_update";

		$sort_data = array('title', 'slug', 'published_at', 'status', 'sort_order');

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY `" . $data['sort'] . "`";
		} else {
			$sql .= " ORDER BY `published_at`";
		}

		if (isset($data['order']) && (strtoupper($data['order']) == 'ASC')) {
			$sql .= " ASC";
		} else {
			$sql .= " DESC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if (!isset($data['start']) || $data['start'] < 0) {
				$data['start'] = 0;
			}

			if (!isset($data['limit']) || $data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalPosts() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "factory_update");

		return $query->row['total'];
	}

	/* ---------------------------------------------------------------------- *
	 * SLUG UNIQUENESS
	 * ---------------------------------------------------------------------- */

	public function slugExists($slug, $exclude_post_id = 0) {
		$query = $this->db->query(
			"SELECT factory_post_id FROM " . DB_PREFIX . "factory_update
			 WHERE slug = '" . $this->db->escape($slug) . "'
			   AND factory_post_id != '" . (int)$exclude_post_id . "'"
		);
		return (bool)$query->num_rows;
	}

	public function slugReservedElsewhere($slug, $exclude_query = '') {
		$sql = "SELECT seo_url_id FROM " . DB_PREFIX . "seo_url
		        WHERE keyword = '" . $this->db->escape($slug) . "'";
		if ($exclude_query !== '') {
			$sql .= " AND query != '" . $this->db->escape($exclude_query) . "'";
		}
		$query = $this->db->query($sql);
		return (bool)$query->num_rows;
	}

	/* ---------------------------------------------------------------------- *
	 * ADD / EDIT
	 * ---------------------------------------------------------------------- */

	public function addPost($data) {
		$published_at = !empty($data['published_at']) ? $data['published_at'] : date('Y-m-d H:i:s');

		$this->db->query("INSERT INTO " . DB_PREFIX . "factory_update SET
			slug             = '" . $this->db->escape($data['slug']) . "',
			title            = '" . $this->db->escape($data['title']) . "',
			excerpt          = '" . $this->db->escape(isset($data['excerpt']) ? $data['excerpt'] : '') . "',
			body_html        = '" . $this->db->escape($data['body_html']) . "',
			hero_image_url   = '" . $this->db->escape(isset($data['hero_image_url']) ? $data['hero_image_url'] : '') . "',
			video_embed      = '" . $this->db->escape(isset($data['video_embed']) ? $data['video_embed'] : '') . "',
			published_at     = '" . $this->db->escape($published_at) . "',
			status           = '" . (int)(isset($data['status']) ? $data['status'] : 1) . "',
			sort_order       = '" . (int)(isset($data['sort_order']) ? $data['sort_order'] : 0) . "',
			meta_title       = '" . $this->db->escape(isset($data['meta_title']) ? $data['meta_title'] : '') . "',
			meta_description = '" . $this->db->escape(isset($data['meta_description']) ? $data['meta_description'] : '') . "',
			author_name      = '" . $this->db->escape(!empty($data['author_name']) ? $data['author_name'] : 'The Miner Island team') . "'");

		$post_id = $this->db->getLastId();

		$this->addSeoUrl((int)$post_id, $data['slug']);

		return $post_id;
	}

	public function editPost($post_id, $data) {
		$post_id = (int)$post_id;

		$existing = $this->getPost($post_id);
		$old_slug = !empty($existing['slug']) ? $existing['slug'] : '';

		$published_at = !empty($data['published_at']) ? $data['published_at'] : date('Y-m-d H:i:s');

		$this->db->query("UPDATE " . DB_PREFIX . "factory_update SET
			slug             = '" . $this->db->escape($data['slug']) . "',
			title            = '" . $this->db->escape($data['title']) . "',
			excerpt          = '" . $this->db->escape(isset($data['excerpt']) ? $data['excerpt'] : '') . "',
			body_html        = '" . $this->db->escape($data['body_html']) . "',
			hero_image_url   = '" . $this->db->escape(isset($data['hero_image_url']) ? $data['hero_image_url'] : '') . "',
			video_embed      = '" . $this->db->escape(isset($data['video_embed']) ? $data['video_embed'] : '') . "',
			published_at     = '" . $this->db->escape($published_at) . "',
			status           = '" . (int)(isset($data['status']) ? $data['status'] : 1) . "',
			sort_order       = '" . (int)(isset($data['sort_order']) ? $data['sort_order'] : 0) . "',
			meta_title       = '" . $this->db->escape(isset($data['meta_title']) ? $data['meta_title'] : '') . "',
			meta_description = '" . $this->db->escape(isset($data['meta_description']) ? $data['meta_description'] : '') . "',
			author_name      = '" . $this->db->escape(!empty($data['author_name']) ? $data['author_name'] : 'The Miner Island team') . "'
			WHERE factory_post_id = '" . $post_id . "'");

		if ($old_slug !== $data['slug']) {
			$this->updateSeoUrl($post_id, $data['slug']);
		} else {
			$this->ensureSeoUrl($post_id, $data['slug']);
		}
	}

	/* ---------------------------------------------------------------------- *
	 * DELETE (Phase 7.1 Step 3)
	 * ---------------------------------------------------------------------- */

	/**
	 * Delete a post and its associated seo_url row.
	 *
	 * Order matters: remove the seo_url row FIRST (so the public site stops
	 * trying to route to a now-missing post the moment one query completes),
	 * THEN remove the post row.
	 */
	public function deletePost($post_id) {
		$post_id = (int)$post_id;
		if ($post_id <= 0) {
			return;
		}

		$this->removeSeoUrl($post_id);

		$this->db->query("DELETE FROM " . DB_PREFIX . "factory_update WHERE factory_post_id = '" . $post_id . "'");
	}

	public function removeSeoUrl($post_id) {
		$query_str = 'factory_post_id=' . (int)$post_id;
		$this->db->query("DELETE FROM " . DB_PREFIX . "seo_url WHERE query = '" . $this->db->escape($query_str) . "'");
	}

	/* ---------------------------------------------------------------------- *
	 * SEO URL MAINTENANCE
	 * ---------------------------------------------------------------------- */

	public function addSeoUrl($post_id, $slug) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "seo_url SET
			store_id    = '0',
			language_id = '1',
			query       = 'factory_post_id=" . (int)$post_id . "',
			keyword     = '" . $this->db->escape($slug) . "'");
	}

	public function updateSeoUrl($post_id, $slug) {
		$query_str = 'factory_post_id=' . (int)$post_id;
		$existing = $this->db->query("SELECT seo_url_id FROM " . DB_PREFIX . "seo_url WHERE query = '" . $this->db->escape($query_str) . "'");

		if ($existing->num_rows) {
			$this->db->query("UPDATE " . DB_PREFIX . "seo_url SET
				keyword = '" . $this->db->escape($slug) . "'
				WHERE query = '" . $this->db->escape($query_str) . "'");
		} else {
			$this->addSeoUrl($post_id, $slug);
		}
	}

	public function ensureSeoUrl($post_id, $slug) {
		$query_str = 'factory_post_id=' . (int)$post_id;
		$existing = $this->db->query("SELECT seo_url_id FROM " . DB_PREFIX . "seo_url WHERE query = '" . $this->db->escape($query_str) . "'");
		if (!$existing->num_rows) {
			$this->addSeoUrl($post_id, $slug);
		}
	}
}
