/*
 * DRESS UP 3D VIEWER — para sa My Orders at lahat ng staff/owner order pages.
 *
 * Hinahanap nito ang bawat <div data-dressup-viewer data-design='{...}'> na
 * inilalabas ng helpers/dressup_helper.php, tapos binubuo ulit ang 3D figure
 * (body + hair/top/bottom/shoes + kulay) gamit ang parehong GLB files ng Dress
 * Up page (Commission/dressup-assets). Kapareho ng pagkulay ng Commission/dressup.js.
 *
 * Lazy: gumagawa lang ng 3D kapag nakikita na ang viewer (hal. binuksan ang
 * order), at pinapakawalan kapag naitago ulit, para hindi maubos ang WebGL
 * contexts ng browser kapag maraming order sa isang page.
 */

import * as THREE from "three";
import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";


const loader = new GLTFLoader();

const SKIP_FRAGMENTS = ["design", "logo", "print"];


function isPlaceholderPath(path) {
    return !path || String(path).startsWith("placeholder:");
}


function loadScene(url) {
    return new Promise((resolve, reject) => {
        loader.load(url, gltf => resolve(gltf.scene), undefined, reject);
    });
}


function hasMeshes(object3D) {
    let count = 0;
    object3D.traverse(node => {
        if (node.isMesh) {
            count += 1;
        }
    });
    return count > 0;
}


function disposeObject(object3D) {
    if (!object3D) {
        return;
    }

    object3D.traverse(node => {
        if (!node.isMesh) {
            return;
        }

        if (node.geometry) {
            node.geometry.dispose();
        }

        const materials = Array.isArray(node.material) ? node.material : [node.material];

        materials.forEach(material => {
            if (!material) {
                return;
            }

            ["map", "normalMap", "roughnessMap", "metalnessMap"].forEach(key => {
                if (material[key]) {
                    material[key].dispose();
                }
            });

            material.dispose();
        });
    });
}


function configureModel(model) {
    model.traverse(node => {
        if (!node.isMesh || !node.material) {
            return;
        }

        // kapareho ng dressup.js: huwag galawin ang acrylic stand ng GLB
        if (String(node.name || "").toLowerCase().includes("acrylic_stand")) {
            return;
        }

        const materials = Array.isArray(node.material) ? node.material : [node.material];

        materials.forEach(material => {
            material.transparent = false;
            material.needsUpdate = true;
        });
    });
}


function shouldSkipMaterial(material, skipFragments) {
    if (!material || !skipFragments.length) {
        return false;
    }

    const name = String(material.name || "").toLowerCase();

    return skipFragments.some(fragment => name.includes(fragment));
}


const TEXTURE_KEYS = [
    "map", "alphaMap", "aoMap", "bumpMap", "displacementMap", "emissiveMap",
    "envMap", "lightMap", "metalnessMap", "normalMap", "roughnessMap",
    "specularMap", "clearcoatMap", "clearcoatNormalMap", "clearcoatRoughnessMap",
    "sheenColorMap", "sheenRoughnessMap", "iridescenceMap",
    "iridescenceThicknessMap", "transmissionMap", "thicknessMap"
];


function recolorMesh(mesh, targetColor, clearTextures, skipFragments) {
    if (!mesh || !mesh.material) {
        return;
    }

    const materials = Array.isArray(mesh.material) ? mesh.material : [mesh.material];

    const next = materials.map(material => {
        if (shouldSkipMaterial(material, skipFragments)) {
            return material;
        }

        const copy = material.clone();

        if (clearTextures) {
            TEXTURE_KEYS.forEach(key => {
                if (copy[key]) {
                    copy[key] = null;
                }
            });
        }

        if (copy.color) {
            copy.color.copy(targetColor);
        }

        copy.needsUpdate = true;

        return copy;
    });

    mesh.material = Array.isArray(mesh.material) ? next : next[0];
}


// kapareho ng applyColorToModel() sa dressup.js
function applyColorToModel(model, color, clearTextures, skipFragments, skipNodeFragments) {
    if (!model || !color) {
        return;
    }

    const targetColor = new THREE.Color(color);
    const skipNodes = skipNodeFragments || [];

    model.traverse(node => {
        const nodeName = String(node.name || "").toLowerCase();

        if (skipNodes.some(fragment => nodeName.includes(fragment))) {
            return;
        }

        if (node.isMesh) {
            recolorMesh(node, targetColor, clearTextures, skipFragments);
        }
    });
}


// kapareho ng applySkinColor() + renderCurrentCategory() sa dressup.js:
// Hirono/Chibi "Light" = orihinal na kulay ng GLB (walang tint), at sa Hirono
// hindi kinukulayan ang stand at sapatos.
const LIGHT_SKIN_COLOR = "#f2ccb7";

function applySkinToBody(body, category, skin) {
    const keepsOriginal = category === "hirono" || category === "chibi";

    if (!skin || (keepsOriginal && String(skin).toLowerCase() === LIGHT_SKIN_COLOR)) {
        return;
    }

    const isHirono = category === "hirono";

    applyColorToModel(
        body,
        skin,
        false,
        isHirono ? ["stand", "pedestal", "support", "shoe", "foot", "sole", "clear_acrylic"] : [],
        isHirono ? ["acrylic_stand", "stand", "pedestal", "support", "shoes", "foot"] : []
    );
}


function isShoeLikeMesh(mesh) {
    const text = [
        mesh.name,
        mesh.parent && mesh.parent.name,
        mesh.material && !Array.isArray(mesh.material) && mesh.material.name
    ].filter(Boolean).join(" ").toLowerCase();

    return /shoe|sneaker|boot|foot|sole/.test(text);
}


// kapareho ng getBottomModelParts() sa dressup.js: pantalon lang ang kukulayan,
// hindi ang sapatos na kasama sa "Pants + Shoes" na model
function getPantsMeshes(model) {
    const meshes = [];

    model.traverse(node => {
        if (node.isMesh) {
            meshes.push(node);
        }
    });

    if (!meshes.length) {
        return [];
    }

    const indexed = meshes.map((mesh, index) => ({
        mesh,
        index,
        centerY: new THREE.Box3().setFromObject(mesh).getCenter(new THREE.Vector3()).y
    })).sort((a, b) => b.centerY - a.centerY);

    const shoeTagged = indexed.filter(item => isShoeLikeMesh(item.mesh));
    const pantsTagged = indexed.filter(item => !isShoeLikeMesh(item.mesh));

    if (shoeTagged.length && pantsTagged.length) {
        return pantsTagged.map(item => item.mesh);
    }

    let splitIndex = 1;
    let largestGap = 0;

    for (let index = 0; index < indexed.length - 1; index += 1) {
        const gap = indexed[index].centerY - indexed[index + 1].centerY;

        if (gap > largestGap) {
            largestGap = gap;
            splitIndex = index + 1;
        }
    }

    if (largestGap <= 0) {
        splitIndex = Math.max(1, Math.ceil(indexed.length / 2));
    } else {
        splitIndex = Math.min(Math.max(1, splitIndex), indexed.length - 1);
    }

    return indexed.slice(0, splitIndex).map(item => item.mesh);
}


class DressUpViewer {

    constructor(stage) {
        this.stage = stage;
        this.canvas = stage.querySelector("canvas");
        this.statusText = stage.querySelector(".dressup-3d-status-text");
        this.assetBase = stage.dataset.assetBase || "";
        this.alive = false;
        this.frameId = 0;
        this.objects = [];

        try {
            this.design = JSON.parse(stage.dataset.design || "{}");
        } catch (error) {
            this.design = {};
        }
    }

    url(path) {
        return this.assetBase + path;
    }

    setStatus(text, isError) {
        if (this.statusText) {
            this.statusText.textContent = text;
        }

        this.stage.classList.toggle("is-error", Boolean(isError));
        this.stage.classList.remove("is-ready");
    }

    start() {
        if (this.alive || !this.canvas) {
            return;
        }

        const width = this.stage.clientWidth;
        const height = this.stage.clientHeight;

        if (width <= 0 || height <= 0) {
            return;
        }

        try {
            this.renderer = new THREE.WebGLRenderer({
                canvas: this.canvas,
                alpha: true,
                antialias: true
            });
        } catch (error) {
            this.setStatus("3D preview is not supported on this browser.", true);
            return;
        }

        this.alive = true;
        this.loadToken = (this.loadToken || 0) + 1;

        this.renderer.outputColorSpace = THREE.SRGBColorSpace;
        this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        this.renderer.setClearColor(0x000000, 0);

        this.scene = new THREE.Scene();
        this.camera = new THREE.PerspectiveCamera(35, width / height, 0.1, 1000);
        this.camera.position.set(0, 1.1, 4.5);

        this.controls = new OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.08;
        this.controls.enablePan = false;
        this.controls.minDistance = 1.5;
        this.controls.maxDistance = 12;

        this.scene.add(new THREE.AmbientLight(0xffffff, 1.6));

        const key = new THREE.DirectionalLight(0xffffff, 1.3);
        key.position.set(4, 6, 6);
        this.scene.add(key);

        const fill = new THREE.DirectionalLight(0xfff0f7, 0.8);
        fill.position.set(-4, 3, 4);
        this.scene.add(fill);

        const rim = new THREE.DirectionalLight(0xffffff, 0.45);
        rim.position.set(0, 4, -5);
        this.scene.add(rim);

        this.resize();

        const loop = () => {
            if (!this.alive) {
                return;
            }

            this.frameId = requestAnimationFrame(loop);
            this.controls.update();
            this.renderer.render(this.scene, this.camera);
        };

        loop();

        void this.load(this.loadToken);
    }

    async load(token) {
        const design = this.design || {};

        if (!design.model) {
            this.setStatus("No 3D design saved for this figure.", true);
            return;
        }

        this.setStatus("Loading 3D figure…", false);

        try {
            let body = await loadScene(this.url(design.model));

            // kapareho ng dressup.js: kung walang laman ang Funko Girl GLB, gamitin ang default
            if (!hasMeshes(body) && /Funko pop-girl\.glb$/i.test(design.model)) {
                disposeObject(body);
                body = await loadScene(this.url("dressup-assets/GLB_files/Funko pop-default.glb"));
            }

            if (!this.alive || token !== this.loadToken) {
                disposeObject(body);
                return;
            }

            configureModel(body);

            if (design.skin) {
                applySkinToBody(body, design.category, design.skin);
            } else if (/Funko pop-girl\.glb$/i.test(design.model)) {
                applyColorToModel(body, "#F2CCB7", false, []);
            }

            this.scene.add(body);
            this.objects.push(body);
            this.frame();

            for (const part of (design.parts || [])) {

                if (isPlaceholderPath(part.model)) {
                    continue;
                }

                try {
                    const object3D = await loadScene(this.url(part.model));

                    if (!this.alive || token !== this.loadToken) {
                        disposeObject(object3D);
                        return;
                    }

                    configureModel(object3D);

                    if (part.isBottom) {
                        if (part.color) {
                            const target = new THREE.Color(part.color);
                            getPantsMeshes(object3D).forEach(mesh => {
                                recolorMesh(mesh, target, true, SKIP_FRAGMENTS);
                            });
                        }
                    } else if (part.color) {
                        applyColorToModel(object3D, part.color, true, SKIP_FRAGMENTS);
                    }

                    this.scene.add(object3D);
                    this.objects.push(object3D);
                } catch (partError) {
                    console.warn("[Dress Up viewer] Could not load part:", part.model, partError);
                }
            }

            this.frame();
            this.stage.classList.remove("is-error");
            this.stage.classList.add("is-ready");

        } catch (error) {
            console.error("[Dress Up viewer] Could not load figure:", error);

            if (this.alive && token === this.loadToken) {
                this.setStatus("3D preview could not be loaded.", true);
            }
        }
    }

    frame() {
        if (!this.objects.length || !this.camera) {
            return;
        }

        const box = new THREE.Box3();
        this.objects.forEach(object3D => box.expandByObject(object3D));

        if (box.isEmpty()) {
            return;
        }

        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());
        const maxSize = Math.max(size.x, size.y, size.z);
        const distance = maxSize / (2 * Math.tan(THREE.MathUtils.degToRad(this.camera.fov / 2)));

        this.camera.position.set(center.x, center.y, center.z + distance * 1.35);
        this.camera.lookAt(center);
        this.controls.target.copy(center);
        this.controls.update();
    }

    resize() {
        if (!this.alive) {
            return;
        }

        const width = this.stage.clientWidth;
        const height = this.stage.clientHeight;

        if (width <= 0 || height <= 0) {
            return;
        }

        this.renderer.setSize(width, height, false);
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
    }

    stop() {
        if (!this.alive) {
            return;
        }

        this.alive = false;
        this.loadToken = (this.loadToken || 0) + 1;

        cancelAnimationFrame(this.frameId);

        this.objects.forEach(object3D => {
            this.scene.remove(object3D);
            disposeObject(object3D);
        });

        this.objects = [];

        if (this.controls) {
            this.controls.dispose();
        }

        if (this.renderer) {
            this.renderer.dispose();
            this.renderer.forceContextLoss();
        }

        // bagong canvas — hindi na magagamit ulit ang canvas na na-"context loss"
        if (this.canvas) {
            const freshCanvas = this.canvas.cloneNode(false);
            this.canvas.replaceWith(freshCanvas);
            this.canvas = freshCanvas;
        }

        this.renderer = null;
        this.scene = null;
        this.camera = null;
        this.controls = null;

        this.setStatus("Loading 3D figure…", false);
    }

    // tinatawag tuwing nagbabago ang laki (kasama ang pagbukas/pagsara ng order)
    sync() {
        const visible = this.stage.clientWidth > 0 && this.stage.clientHeight > 0;

        if (visible && !this.alive) {
            this.start();
        } else if (!visible && this.alive) {
            this.stop();
        } else if (visible) {
            this.resize();
        }
    }
}


const viewers = new Map();

const resizeObserver = typeof ResizeObserver !== "undefined"
    ? new ResizeObserver(entries => {
        entries.forEach(entry => {
            const viewer = viewers.get(entry.target);
            if (viewer) {
                viewer.sync();
            }
        });
    })
    : null;


function setupViewers(root) {
    (root || document).querySelectorAll("[data-dressup-viewer]").forEach(stage => {
        if (viewers.has(stage)) {
            return;
        }

        const viewer = new DressUpViewer(stage);
        viewers.set(stage, viewer);

        if (resizeObserver) {
            resizeObserver.observe(stage);
        }

        viewer.sync();
    });
}


// fallback para sa lumang browser na walang ResizeObserver
if (!resizeObserver) {
    setInterval(() => viewers.forEach(viewer => viewer.sync()), 800);
}


if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => setupViewers(document));
} else {
    setupViewers(document);
}

window.figurifySetupDressUpViewers = setupViewers;
