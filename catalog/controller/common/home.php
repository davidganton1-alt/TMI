<?php
class ControllerCommonHome extends Controller {
	public function index() {
		$this->document->setTitle($this->config->get('config_meta_title'));
		$this->document->setDescription($this->config->get('config_meta_description'));
		$this->document->setKeywords($this->config->get('config_meta_keyword'));

		if (isset($this->request->get['route'])) {
			$this->document->addLink($this->config->get('config_url'), 'canonical');
		}

		// ─── MinerIsland Phase 7: latest Factory Update for homepage trust pair ───
		// One DB hit; model query is indexed on status + published_at. Falls
		// through silently if the table is empty or the model is missing.
		$data['latest_post'] = null;
		try {
			$this->load->model('extension/module/factory_updates');
			$latest = $this->model_extension_module_factory_updates->getLatestPost();
			if ($latest) {
				$hero = $latest['hero_image_url'];
				$hero_resolved = $hero;
				if ($hero) {
					$abs = realpath(DIR_APPLICATION . '../' . $hero);
					if ($abs === false || !is_file($abs)) {
						$hero_resolved = ''; // template will show CSS placeholder
					}
				}
				$data['latest_post'] = array(
					'title'      => $latest['title'],
					'excerpt'    => $latest['excerpt'],
					'date'       => date('F j, Y', strtotime($latest['published_at'])),
					'hero_image' => $hero_resolved,
					'href'       => $this->url->link('extension/module/factory_updates', 'factory_post_id=' . (int)$latest['factory_post_id']),
				);
			}
		} catch (Exception $e) {
			// Silent fail — homepage continues to render without the card pair
			$data['latest_post'] = null;
		}

		// URLs for the team card (always-on, hardcoded headshots)
		$data['team_url'] = $this->url->link('information/information', 'information_id=13');
		$data['factory_updates_url'] = $this->url->link('extension/module/factory_updates');
		// ─── END Phase 7 addition ────────────────────────────────────────────

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/home', $data));
	}
}
